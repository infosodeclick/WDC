<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOnboardingRequest;
use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\ItAsset;
use App\Models\MeetingRoomBooking;
use App\Models\Permission;
use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Services\GoogleCalendarService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WdcPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_login_with_employee_code_and_view_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->followingRedirects()->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertDontSee('Dashboard')
            ->assertDontSee('search-box', false)
            ->assertDontSee('metric-grid', false)
            ->assertDontSee('ประกาศใหม่')
            ->assertDontSee('งาน IT ค้าง')
            ->assertDontSee('วิดีโอเทรนนิ่งใหม่')
            ->assertDontSee('ข้อมูลติดต่อ')
            ->assertDontSee('คำขอของฉัน')
            ->assertDontSee('คำขอ/อนุมัติของฉัน')
            ->assertDontSee('ระบบที่ใช้งานบ่อย')
            ->assertDontSee('เปิดศูนย์คำขอ')
            ->assertDontSee('ดูศูนย์รวมระบบ')
            ->assertDontSee('ส่งคำขออนุมัติ')
            ->assertDontSee('เข้าระบบเดิม')
            ->assertDontSee('system-mini-grid', false)
            ->assertDontSee('meeting-room-panel', false)
            ->assertDontSee('href="#meeting-room"', false)
            ->assertSee('สวัสดี คุณสมชาย')
            ->assertSee('โปรไฟล์พนักงาน');
    }

    public function test_profile_phone_change_is_approved_by_hr_before_updating_employee(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $this->actingAs($employee);

        $this->patch(route('profile.contact.update'), [
            'phone' => '099-111-2222',
        ])->assertRedirect();

        $profileRequest = ProfileChangeRequest::where('user_id', $employee->id)
            ->where('field', 'phone')
            ->firstOrFail();

        $this->assertSame('pending', $profileRequest->status);
        $this->assertSame('099-111-2222', $profileRequest->requested_value);
        $this->assertNotSame('099-111-2222', $employee->employee->fresh()->phone);

        $this->actingAs($hr);

        $this->patch(route('hr.profile-requests.review', $profileRequest), [
            'status' => 'approved',
        ])->assertRedirect();

        $profileRequest->refresh();

        $this->assertSame('approved', $profileRequest->status);
        $this->assertSame('099-111-2222', $employee->employee->fresh()->phone);
    }

    public function test_profile_announcements_track_reads_and_keep_urgent_items_visible(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $urgent = Announcement::where('is_urgent', true)->firstOrFail();
        $general = Announcement::where('is_urgent', false)->where('category', 'ประกาศ')->firstOrFail();

        $this->actingAs($employee);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee($urgent->title)
            ->assertSee($general->title)
            ->assertSee(route('payroll'), false)
            ->assertSee(route('time-attendance'), false);

        $this->get(route('announcements.show', $general))->assertOk();

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $general->id,
            'user_id' => $employee->id,
        ]);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee($urgent->title)
            ->assertDontSee($general->title);

        $this->get(route('announcements.show', $urgent))->assertOk();

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $urgent->id,
            'user_id' => $employee->id,
        ]);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee($urgent->title);
    }

    public function test_dashboard_announcement_popup_has_close_arrows_and_indicators(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $response = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-auto-open-announcement-modal', false)
            ->assertSee('data-announcement-popup-close', false)
            ->assertSee('data-announcement-popup-prev', false)
            ->assertSee('data-announcement-popup-next', false)
            ->assertSee('announcement-carousel-indicators', false);

        $this->assertSame(3, substr_count($response->getContent(), 'data-announcement-popup-dot='));
    }

    public function test_hr_can_create_announcement_with_uploaded_attachment(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $this->actingAs($hr);

        $this->post(route('hr.announcements.store'), [
            'announcement_no' => 'HR-ACT-TEST-001',
            'title' => 'Activity Upload Test',
            'category' => 'กิจกรรม',
            'body' => 'Attachment smoke test',
            'files' => [UploadedFile::fake()->image('activity.png')],
            'is_pinned' => '1',
            'is_urgent' => '1',
            'popup_enabled' => '1',
        ])->assertRedirect();

        $announcement = Announcement::where('announcement_no', 'HR-ACT-TEST-001')->firstOrFail();
        $file = AnnouncementFile::where('announcement_id', $announcement->id)->firstOrFail();

        Storage::disk('local')->assertExists($file->file_path);
        $this->assertTrue($announcement->is_pinned);
        $this->assertTrue($announcement->is_urgent);
        $this->assertTrue($announcement->popup_enabled);
        $this->assertSame('activity.png', $file->file_name);

        $this->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('Activity Upload Test')
            ->assertSee('activity.png')
            ->assertSee(route('announcements.files.show', $file), false);
    }

    public function test_hr_backend_uses_dashboard_and_section_menus(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $this->actingAs($hr);

        $this->get(route('hr.index'))
            ->assertOk()
            ->assertSee('แดชบอร์ด')
            ->assertSee('พนักงานทั้งหมด')
            ->assertSee('คำขอแก้โปรไฟล์')
            ->assertDontSee('ระบบที่ให้ IT เปิด')
            ->assertDontSee('เลขที่ประกาศ');

        $this->get(route('hr.index', ['section' => 'onboarding']))
            ->assertOk()
            ->assertSee('ชื่อ ภาษาอังกฤษ')
            ->assertSee('นามสกุล ภาษาอังกฤษ')
            ->assertSee('ชื่อเล่น ภาษาไทย')
            ->assertSee('เบอร์โต๊ะ')
            ->assertSee('เลือกตำแหน่ง')
            ->assertSee('เลือกแผนก/BU')
            ->assertDontSee('ระบบที่ให้ IT เปิด')
            ->assertDontSee('เลขที่ประกาศ');

        $this->get(route('hr.index', ['section' => 'announcements']))
            ->assertOk()
            ->assertSee('เลขที่ประกาศ')
            ->assertDontSee('ระบบที่ให้ IT เปิด');

        $this->get(route('hr.index', ['section' => 'employees']))
            ->assertOk()
            ->assertSee('รายชื่อพนักงาน')
            ->assertSee('EMP00125')
            ->assertSee('สมชาย ใจดี')
            ->assertDontSee('administrator ·')
            ->assertDontSee('เลขที่ประกาศ');
    }

    public function test_admin_can_open_admin_portal(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP09999',
            'password' => 'password123',
        ]);

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('ระบบ')
            ->assertSee('เมนูด้านซ้ายหน้าบ้าน')
            ->assertSee('portal.dashboard.view')
            ->assertSee(route('admin.index', ['section' => 'create-user']), false)
            ->assertDontSee('สร้างบัญชี WDC Login')
            ->assertDontSee('แสดงในรายชื่อ / ใช้งาน')
            ->assertDontSee('Super Admin Console')
            ->assertDontSee('ศูนย์หลังบ้านและสิทธิ์ผู้ใช้งาน')
            ->assertDontSee('ผู้ใช้งานทั้งหมด')
            ->assertDontSee('Admin Access');

        $this->get(route('admin.index', ['section' => 'create-user']))
            ->assertOk()
            ->assertSee('เพิ่มผู้ใช้งาน')
            ->assertSee('สร้างบัญชี WDC Login')
            ->assertSee('ชื่อเล่นอังกฤษ')
            ->assertSee('ชื่อเล่นไทย')
            ->assertDontSee('เมนูด้านซ้ายหน้าบ้าน');

        $this->get(route('admin.index', ['section' => 'permissions']))
            ->assertOk()
            ->assertSee('กำหนดสิทธิ์')
            ->assertSee('รายชื่อพนักงาน')
            ->assertSee('แสดงในรายชื่อ / ใช้งาน')
            ->assertSee('EMP00125');

        $this->get(route('admin.index', ['section' => 'role-template']))
            ->assertOk()
            ->assertSee('Role Template');

        $this->get(route('admin.index', ['section' => 'activity-logs']))
            ->assertOk()
            ->assertSee('Activity Logs');
    }

    public function test_admin_can_create_employee_with_language_specific_nicknames_for_directory(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('employee_code', 'EMP09999')->firstOrFail();
        $departmentId = $admin->employee->department_id;
        $roleId = $admin->role_id;
        $this->actingAs($admin);

        $this->post(route('admin.users.store'), [
            'employee_code' => 'EMP08888',
            'name' => 'ณัฐวดี ทดสอบ',
            'email' => 'nattawadee.test@wdc.co.th',
            'password' => 'password123',
            'role_id' => $roleId,
            'data_scope' => '',
            'department_id' => $departmentId,
            'english_name' => 'Nattawadee Test',
            'english_nickname' => 'Nat',
            'thai_name' => 'ณัฐวดี ทดสอบ',
            'thai_nickname' => 'นัท',
            'position' => 'HR Officer',
            'phone' => '080-888-8888',
            'extension_number' => '1888',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'english_name' => 'Nattawadee Test',
            'english_nickname' => 'Nat',
            'thai_name' => 'ณัฐวดี ทดสอบ',
            'thai_nickname' => 'นัท',
        ]);

        $this->assertDatabaseHas('employee_directory_entries', [
            'source_system' => 'wdc',
            'source_record_id' => 'EMP08888',
            'english_name' => 'Nattawadee Test',
            'english_nickname' => 'Nat',
            'thai_name' => 'ณัฐวดี ทดสอบ',
            'thai_nickname' => 'นัท',
        ]);

        $this->get(route('directory.index', ['q' => 'Nattawadee']))
            ->assertOk()
            ->assertSee('Nattawadee Test (Nat)')
            ->assertSee('ณัฐวดี ทดสอบ (นัท)')
            ->assertDontSee('Nattawadee Test (นัท)');
    }

    public function test_granular_hr_and_it_permissions_are_seeded_for_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $hrRole = Role::where('slug', 'hr')->with('permissions')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->with('permissions')->firstOrFail();
        $iamRole = Role::where('slug', 'iam_admin')->with('permissions')->firstOrFail();
        $auditorRole = Role::where('slug', 'auditor')->with('permissions')->firstOrFail();
        $assetOfficerRole = Role::where('slug', 'it_asset_officer')->with('permissions')->firstOrFail();
        $assetAdminRole = Role::where('slug', 'it_asset_admin')->with('permissions')->firstOrFail();

        $this->assertTrue($hrRole->permissions->contains('key', 'directory.manage'));
        $this->assertTrue($hrRole->permissions->contains('key', 'hr.onboarding.manage'));
        $this->assertTrue($adminRole->permissions->contains('key', 'assets.settings.manage'));
        $this->assertTrue($adminRole->permissions->contains('key', 'assets.delete'));
        $this->assertTrue($iamRole->permissions->contains('key', 'iam.users.manage'));
        $this->assertTrue($iamRole->permissions->contains('key', 'iam.roles.manage'));
        $this->assertTrue($auditorRole->permissions->contains('key', 'audit.logs.view'));
        $this->assertTrue($auditorRole->permissions->contains('key', 'audit.logs.export'));
        $this->assertFalse($auditorRole->permissions->contains('key', 'admin.users.manage'));
        $this->assertTrue($assetOfficerRole->permissions->contains('key', 'assets.manage'));
        $this->assertTrue($assetOfficerRole->permissions->contains('key', 'it.onboarding.manage'));
        $this->assertFalse($assetOfficerRole->permissions->contains('key', 'assets.delete'));
        $this->assertTrue($assetAdminRole->permissions->contains('key', 'assets.settings.manage'));
        $this->assertTrue($assetAdminRole->permissions->contains('key', 'assets.delete'));
        $this->assertFalse($employee->effectivePermissionKeys()->contains('directory.manage'));
        $this->assertTrue($itUser->effectivePermissionKeys()->contains('assets.settings.manage'));
        $this->assertTrue($itUser->effectivePermissionKeys()->contains('assets.delete'));
    }

    public function test_iam_and_auditor_roles_can_open_admin_without_business_admin_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $employee->update([
            'role_id' => Role::where('slug', 'auditor')->value('id'),
            'data_scope' => 'all',
        ]);
        $employee->permissionOverrides()->sync([]);

        $this->actingAs($employee);

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Activity Log')
            ->assertDontSee('เพิ่มผู้ใช้งาน');
    }

    public function test_hr_can_create_employee_user_without_admin_role_escalation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hrUser = User::where('employee_code', 'EMP01000')->firstOrFail();
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $departmentId = $hrUser->employee->department_id;

        $this->actingAs($hrUser);

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('directory.manage');

        $this->get(route('admin.index', ['section' => 'create-user']))
            ->assertOk()
            ->assertSee('เพิ่มผู้ใช้งาน');

        $payload = [
            'employee_code' => 'EMP07777',
            'name' => 'Test HR Created',
            'email' => 'test.hr.created@wdc.co.th',
            'password' => 'password123',
            'role_id' => $employeeRole->id,
            'data_scope' => '',
            'department_id' => $departmentId,
            'english_name' => 'Test HR Created',
            'english_nickname' => 'Test',
            'thai_name' => 'ทดสอบ HR',
            'thai_nickname' => 'เทส',
            'position' => 'HR Created Employee',
        ];

        $this->post(route('admin.users.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('users', [
            'employee_code' => 'EMP07777',
            'role_id' => $employeeRole->id,
        ]);

        $this->post(route('admin.users.store'), [
            ...$payload,
            'employee_code' => 'EMP07778',
            'email' => 'blocked.admin.created@wdc.co.th',
            'role_id' => $adminRole->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['employee_code' => 'EMP07778']);
    }

    public function test_hr_it_onboarding_flow_creates_employee_directory_entry_and_links_asset(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $departmentId = $hr->employee->department_id;
        $asset = ItAsset::where('code', 'WDC-NB-0001')->firstOrFail();

        $this->actingAs($hr);

        $this->post(route('hr.onboarding.store'), [
            'employee_code' => 'EMP77777',
            'english_first_name' => 'New',
            'english_last_name' => 'Starter',
            'thai_first_name' => 'พนักงาน',
            'thai_last_name' => 'ใหม่',
            'english_nickname' => 'New',
            'thai_nickname' => 'ใหม่',
            'department_id' => $departmentId,
            'position' => 'Sales Executive',
            'team' => 'Team A',
            'location' => 'Lumpini',
            'corporate_email' => 'new.starter@wdc.co.th',
            'personal_phone' => '099-777-7777',
            'extension_number' => '7777',
            'start_date' => now()->toDateString(),
            'requested_systems' => ['WDC Portal', 'Email', 'ERP'],
            'hr_note' => 'เริ่มงานเดือนนี้',
        ])->assertRedirect();

        $onboarding = EmployeeOnboardingRequest::with('systems')->where('employee_code', 'EMP77777')->firstOrFail();
        $administrator = User::where('employee_code', 'administrator')->firstOrFail();

        $this->assertSame('pending_it', $onboarding->status);
        $this->assertCount(3, $onboarding->systems);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $administrator->id,
            'type' => 'onboarding',
            'title' => 'มีรายการพนักงานใหม่จาก HR',
            'url' => route('onboarding.show', $onboarding),
        ]);

        $this->actingAs($administrator);

        $this->get(route('admin.index', ['section' => 'notifications']))
            ->assertOk()
            ->assertSee('แจ้งเตือนแอดมิน')
            ->assertSee('คำขอพนักงานใหม่ที่รอ IT')
            ->assertSee('EMP77777')
            ->assertSee('New Starter');

        $this->get(route('onboarding.show', $onboarding))
            ->assertOk()
            ->assertSee('ข้อมูลที่ HR ส่งมา')
            ->assertSee('EMP77777')
            ->assertSee('New Starter')
            ->assertSee('พนักงาน ใหม่')
            ->assertSee('เริ่มงานเดือนนี้')
            ->assertSee('อนุมัติเปิดระบบและส่งกลับ HR');

        $emailSystem = $onboarding->systems->firstWhere('system_name', 'Email');

        $this->actingAs($itUser);

        $this->get(route('onboarding.show', $onboarding))
            ->assertOk()
            ->assertSee('รายการเปิดระบบโดย IT')
            ->assertSee('บันทึกข้อมูล IT');

        $this->patch(route('it.onboarding.update', $onboarding), [
            'systems' => [
                $emailSystem->id => [
                    'status' => 'provisioned',
                    'username' => 'new.starter',
                    'email' => 'new.starter@wdc.co.th',
                    'it_asset_id' => $asset->id,
                    'notes' => 'เปิดอีเมลและมอบเครื่องแล้ว',
                ],
            ],
            'it_note' => 'พร้อมให้ HR ตรวจสอบ',
        ])->assertRedirect();

        $this->patch(route('it.onboarding.complete', $onboarding))->assertRedirect();

        $this->assertSame('it_completed', $onboarding->fresh()->status);

        $this->actingAs($hr);

        $this->patch(route('hr.onboarding.publish', $onboarding), [
            'photo' => UploadedFile::fake()->image('new-starter.jpg', 640, 640),
            'hr_note' => 'อนุมัติแสดงรายชื่อ',
        ])->assertRedirect();

        $onboarding->refresh();
        $createdUser = User::where('employee_code', 'EMP77777')->firstOrFail();

        $this->assertSame('hr_approved', $onboarding->status);
        $this->assertSame('New Starter', $createdUser->name);
        $this->assertDatabaseHas('employees', [
            'user_id' => $createdUser->id,
            'english_name' => 'New Starter',
            'thai_nickname' => 'ใหม่',
        ]);
        $this->assertDatabaseHas('employee_directory_entries', [
            'source_system' => 'wdc',
            'source_record_id' => 'EMP77777',
            'display_name' => 'New Starter',
            'employment_status' => 'active',
            'is_active' => true,
        ]);
        $this->assertSame($createdUser->id, $asset->fresh()->owner_id);

        $this->get(route('directory.index', ['q' => 'New Starter']))
            ->assertOk()
            ->assertSee('New Starter (New)')
            ->assertSee('พนักงาน ใหม่ (ใหม่)');
    }

    public function test_legacy_systems_hub_is_removed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->get('/systems')->assertNotFound();
    }

    public function test_user_permission_override_can_block_frontend_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $permission = Permission::where('key', 'knowledge.view')->firstOrFail();

        $employee->permissionOverrides()->sync([
            $permission->id => ['effect' => 'deny'],
        ]);

        $this->actingAs($employee);

        $this->get(route('knowledge.index'))->assertForbidden();
    }

    public function test_dashboard_hides_shortcuts_for_denied_menu_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $permissions = Permission::whereIn('key', ['knowledge.view'])->pluck('id');

        $employee->permissionOverrides()->sync(
            $permissions->mapWithKeys(fn (int $permissionId) => [$permissionId => ['effect' => 'deny']])->all(),
        );

        $this->actingAs($employee);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('knowledge.index'), false)
            ->assertDontSee('เข้าระบบเดิม');
    }

    public function test_employee_can_open_meeting_room_menu_page(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('meeting-rooms.index'), false)
            ->assertDontSee('href="#meeting-room"', false)
            ->assertDontSee('meeting-room-panel', false);

        $this->get(route('meeting-rooms.index'))
            ->assertOk()
            ->assertSee('ห้องประชุม')
            ->assertSee('Google Calendar')
            ->assertSee('ตารางจองห้องประชุม')
            ->assertSee('จองห้องประชุม')
            ->assertSee('meetingRoomBookingModal', false)
            ->assertSee('room_name', false)
            ->assertSee('start_at', false)
            ->assertSee('end_at', false)
            ->assertSee('calendar.google.com/calendar/u/0/embed', false)
            ->assertSee('641a219d5e8a0c60b9107fff5f155eba12e1d82d03809d7df47bc8aa656ea1e6', false)
            ->assertDontSee('calendar.google.com/calendar/u/0/r/eventedit', false);
    }

    public function test_meeting_room_page_respects_permission_override(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $permission = Permission::where('key', 'meeting_rooms.view')->firstOrFail();

        $employee->permissionOverrides()->sync([
            $permission->id => ['effect' => 'deny'],
        ]);

        $this->actingAs($employee);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('meeting-rooms.index'), false);

        $this->get(route('meeting-rooms.index'))->assertForbidden();
    }

    public function test_employee_can_submit_meeting_room_booking_in_modal_flow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakeGoogleCalendarSync();

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->post(route('meeting-rooms.store'), [
            'room_name' => 'ห้องประชุมใหญ่',
            'title' => 'ประชุมทดสอบระบบจอง',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'attendees' => 8,
            'notes' => 'ขอใช้จอประชุม',
        ])->assertRedirect(route('meeting-rooms.index').'#wdc-bookings');

        $booking = MeetingRoomBooking::where('title', 'ประชุมทดสอบระบบจอง')->firstOrFail();

        $this->assertSame($employee->id, $booking->user_id);
        $this->assertSame('ห้องประชุมใหญ่', $booking->room_name);
        $this->assertSame('synced', $booking->status);
        $this->assertSame('google-event-1', $booking->google_event_id);

        $this->get(route('meeting-rooms.index'))
            ->assertOk()
            ->assertSee('wdc-bookings', false)
            ->assertSee('ประชุมทดสอบระบบจอง')
            ->assertSee('ห้องประชุมใหญ่')
            ->assertSee('ซิงค์แล้ว')
            ->assertSee(route('meeting-rooms.cancel', $booking), false)
            ->assertDontSee('รอซิงค์');

        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $this->actingAs($itUser);

        $this->get(route('meeting-rooms.index'))
            ->assertOk()
            ->assertDontSee('ประชุมทดสอบระบบจอง');
    }

    public function test_employee_can_cancel_own_meeting_room_booking_and_remove_google_event(): void
    {
        $this->seed(DatabaseSeeder::class);
        $calendar = $this->fakeGoogleCalendarSync();

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $booking = MeetingRoomBooking::create([
            'user_id' => $employee->id,
            'room_name' => 'ห้องประชุมใหญ่',
            'title' => 'ยกเลิกประชุมทดสอบ',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'attendees' => 4,
            'status' => 'synced',
            'google_event_id' => 'google-event-cancel',
            'synced_at' => now(),
        ]);

        $this->patch(route('meeting-rooms.cancel', $booking))
            ->assertRedirect(route('meeting-rooms.index').'#wdc-bookings');

        $booking->refresh();

        $this->assertSame('cancelled', $booking->status);
        $this->assertSame($employee->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);

        $this->assertContains('google-event-cancel', $calendar->deletedEvents);

        $this->get(route('meeting-rooms.index'))
            ->assertOk()
            ->assertSee('ยกเลิกประชุมทดสอบ')
            ->assertSee('ยกเลิกแล้ว')
            ->assertDontSee('ยืนยันยกเลิกการจองนี้?');
    }

    private function fakeGoogleCalendarSync(): object
    {
        $calendar = new class extends GoogleCalendarService
        {
            public array $deletedEvents = [];

            public function createEvent(MeetingRoomBooking $booking): string
            {
                return 'google-event-1';
            }

            public function deleteEvent(string $eventId): void
            {
                $this->deletedEvents[] = $eventId;
            }
        };

        $this->app->instance(GoogleCalendarService::class, $calendar);

        return $calendar;
    }

    public function test_mobile_navigation_respects_user_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('mobile-bottom-nav', false)
            ->assertSee(route('profile'), false)
            ->assertDontSee(route('assets.index'), false);

        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $this->actingAs($itUser);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('mobile-bottom-nav', false)
            ->assertSee(route('assets.index'), false);
    }

    public function test_dashboard_has_profile_button_and_profile_page_opens(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('profile'), false)
            ->assertSee('page-profile-button', false)
            ->assertDontSee('role-badge', false);

        $this->get(route('profile'))
            ->assertOk()
            ->assertDontSee('Employee Profile')
            ->assertSee('EMP00125');
    }

    public function test_marking_notifications_read_does_not_flash_message(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $response = $this->post(route('notifications.read'));

        $response->assertRedirect();
        $this->assertFalse(session()->has('status'));
    }

    public function test_forms_page_groups_documents_by_business_category(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        $this->get(route('documents.index'))
            ->assertOk()
            ->assertSee('document-category-grid', false)
            ->assertSee('ทั้งหมด')
            ->assertSee('HR')
            ->assertSee('บัญชี')
            ->assertSee('HR / ใบลา')
            ->assertSee('บัญชี / เบิกเงินสดย่อย')
            ->assertSee('leave-form.pdf')
            ->assertSee('petty-cash-form.pdf')
            ->assertDontSee('Forms')
            ->assertDontSee('ใบลา ระเบียบบริษัท คู่มือพนักงาน สัญญาจ้าง และหนังสือรับรอง');

        $this->get(route('documents.index', ['department' => 'HR']))
            ->assertOk()
            ->assertSee('HR / ใบลา')
            ->assertSee('leave-form.pdf')
            ->assertDontSee('บัญชี / เบิกเงินสดย่อย')
            ->assertDontSee('petty-cash-form.pdf');
    }

    public function test_search_only_returns_modules_visible_to_current_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $permissions = Permission::whereIn('key', ['announcements.view', 'knowledge.view', 'directory.view'])->pluck('id');

        $employee->permissionOverrides()->sync(
            $permissions->mapWithKeys(fn (int $permissionId) => [$permissionId => ['effect' => 'deny']])->all(),
        );

        $this->actingAs($employee);

        $this->get(route('search', ['q' => 'ERP']))
            ->assertOk()
            ->assertDontSee('ปรับปรุงระบบ ERP')
            ->assertDontSee('วิธีเปิดใบสั่งขาย')
            ->assertDontSee('Chanapon Jakkaphan');
    }

    public function test_employee_can_search_imported_directory(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index', ['q' => 'Chanapon']))
            ->assertOk()
            ->assertSee('Chanapon Jakkaphan')
            ->assertSee('Information Technology')
            ->assertSee(route('profile'), false)
            ->assertDontSee('Employee ·')
            ->assertDontSee('WDC Information Directory')
            ->assertDontSee('ข้อมูลจาก Notion เดิมถูกนำเข้ามาไว้ใน WDC Portal')
            ->assertDontSee('page-heading', false)
            ->assertDontSee('search-box', false)
            ->assertDontSee('By location')
            ->assertDontSee('All team members')
            ->assertDontSee('By team')
            ->assertDontSee('Table view')
            ->assertDontSee('directory-view-tab', false)
            ->assertDontSee('directory-metrics', false)
            ->assertSee('directory-filter-search', false)
            ->assertSee('directory-quick-links', false)
            ->assertSee(route('directory.index', ['type' => 'mail_group']), false)
            ->assertSee(route('directory.index', ['type' => 'showroom']), false)
            ->assertSee('Group Mail')
            ->assertDontSee('role-badge', false)
            ->assertDontSee('ข้อมูลทั้งหมด')
            ->assertDontSee('นำเข้าจาก Notion')
            ->assertDontSee('อัปเดตล่าสุด')
            ->assertSee('รหัสพนักงาน')
            ->assertSee('เบอร์โทรโต๊ะ')
            ->assertDontSee('ข้อมูลต้นทาง')
            ->assertDontSee('class="tag"', false)
            ->assertSee('pdpa-note-toggle', false)
            ->assertSee('ข้อมูลนี้ใช้เพื่อการติดต่อและประสานงานภายในองค์กรเท่านั้น ห้ามเผยแพร่ คัดลอก หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาตจากบริษัท');
    }

    public function test_directory_quick_links_show_mail_groups_and_branch_entries(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index', ['type' => 'mail_group']))
            ->assertOk()
            ->assertSee('directory-quick-link active', false)
            ->assertSee('accountwdc@wdc.co.th')
            ->assertSee('all@wdc.co.th')
            ->assertDontSee('Flagship Showroom');

        $this->get(route('directory.index', ['type' => 'showroom']))
            ->assertOk()
            ->assertSee('directory-quick-link active', false)
            ->assertSee('Flagship Showroom')
            ->assertSee('02-407-9085')
            ->assertDontSee('accountwdc@wdc.co.th');
    }

    public function test_employee_can_switch_directory_display_modes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index', ['view' => 'team', 'q' => 'Chanapon']))
            ->assertOk()
            ->assertDontSee('By team')
            ->assertDontSee('directory-group', false)
            ->assertDontSee('directory-group-name', false)
            ->assertSee('Chanapon Jakkaphan');

        $this->get(route('directory.index', ['view' => 'location', 'q' => 'Chanapon']))
            ->assertOk()
            ->assertDontSee('By location')
            ->assertDontSee('directory-group', false)
            ->assertDontSee('directory-group-count', false)
            ->assertSee('Chanapon Jakkaphan');

        $this->get(route('directory.index', ['view' => 'table', 'q' => 'Chanapon']))
            ->assertOk()
            ->assertDontSee('Table view')
            ->assertDontSee('directory-table', false)
            ->assertSee('Chanapon Jakkaphan')
            ->assertSee('Information Technology');
    }

    public function test_directory_prioritizes_current_month_new_hires_with_compact_cards(): void
    {
        $this->seed(DatabaseSeeder::class);

        EmployeeDirectoryEntry::create([
            'source_system' => 'manual',
            'source_record_id' => 'new-hire-this-month',
            'entry_type' => 'employee',
            'display_name' => 'Newest WDC Member',
            'english_name' => 'Newest WDC Member',
            'english_nickname' => 'New',
            'thai_name' => 'สมาชิกใหม่ ดับบลิวดีซี',
            'thai_nickname' => 'ใหม่',
            'department' => 'Human Resources',
            'position' => 'HR Officer',
            'location' => 'Lumpini',
            'email' => 'new.member@wdc.co.th',
            'phone' => '099-000-0000',
            'extension_number' => '1234',
            'raw_payload' => [
                'start_date' => now()->toDateString(),
                'employee_code' => 'EMP00999',
            ],
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index', ['type' => 'employee']))
            ->assertOk()
            ->assertSeeInOrder(['Newest WDC Member', 'Bundit Hirunyanitiwatna'])
            ->assertSee('Newest WDC Member (New)')
            ->assertSee('สมาชิกใหม่ ดับบลิวดีซี (ใหม่)')
            ->assertDontSee('Newest WDC Member (ใหม่)')
            ->assertSee('new-hire-badge', false)
            ->assertDontSee('directory-card-detail', false)
            ->assertSee('role="button"', false)
            ->assertSee('directory-modal-source', false)
            ->assertSee('directory-modal-highlight-list', false)
            ->assertDontSee('mini-detail-list', false);
    }

    public function test_employee_can_create_workflow_request_from_smartflow_template(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->post(route('workflows.store'), [
            'workflow_template_id' => $template->id,
            'title' => 'ขออนุมัติ Remote Access',
            'details' => 'ต้องการให้ IT remote เพื่อแก้ปัญหา SAP B1',
            'form_payload' => [
                'ระบบที่เกี่ยวข้อง' => 'SAP B1',
                'วันที่ต้องการ' => '20/06/2026',
            ],
            'priority' => 'high',
            'legacy_reference' => 'REF: #2606815',
        ])->assertRedirect(route('workflows.index'));

        $workflowRequest = WorkflowRequest::where('title', 'ขออนุมัติ Remote Access')->firstOrFail();

        $this->assertSame('submitted', $workflowRequest->status);
        $this->assertSame('REF: #2606815', $workflowRequest->legacy_reference);
        $this->assertStringStartsWith('WDC-SF-', $workflowRequest->document_number);
        $this->assertSame('SAP B1', $workflowRequest->form_payload['ระบบที่เกี่ยวข้อง']);
        $this->assertNotNull($workflowRequest->current_step_id);
    }

    public function test_smartflow_catalog_syncs_live_workflow_fields_and_branches(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, WorkflowTemplate::where('source_system', 'smartflow')->count());
        $this->assertTrue(WorkflowTemplate::where('legacy_workflow_id', '5')->where('name', 'ใบคืนสินค้า')->exists());

        $template = WorkflowTemplate::where('legacy_workflow_id', '7')->firstOrFail();
        $fields = collect($template->schemaFieldDefinitions());

        $this->assertTrue($fields->contains(fn (array $field) => $field['key'] === 'dynamic_181' && $field['type'] === 'checkbox'));
        $this->assertTrue($fields->contains(fn (array $field) => $field['key'] === 'dynamic_62' && $field['type'] === 'rich_text'));
        $this->assertNotEmpty($template->routingRules());
        $this->assertTrue($template->steps()->where('external_step_id', '67')->where('name', 'AI-CRM Accept Case')->exists());
        $this->assertTrue($template->steps()->where('external_step_id', '68')->where('name', 'Softpowerit Accept Case')->exists());
    }

    public function test_super_admin_can_resync_smartflow_catalog_from_backend(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP09999',
            'password' => 'password123',
        ]);

        $this->post(route('workflows.templates.sync-smartflow'))->assertRedirect();

        $template = WorkflowTemplate::where('legacy_workflow_id', '14')->firstOrFail();

        $this->assertSame('ขออนุมัติคอนเทนฅ์ (Marketing)', $template->name);
        $this->assertTrue($template->steps()->where('external_step_id', '74')->where('condition_label', 'content Equals "WDC"')->exists());
    }

    public function test_manager_can_update_workflow_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();
        $manager = User::where('employee_code', 'EMP00200')->firstOrFail();
        $workflowRequest = WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $manager->id,
            'current_step_id' => $template->steps()->first()?->id,
            'title' => 'ทดสอบอัปเดต workflow',
            'details' => 'คำขอสำหรับทดสอบสถานะ',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00200',
            'password' => 'password123',
        ]);

        $this->patch(route('workflows.status', $workflowRequest), [
            'status' => 'in_review',
            'comment' => 'รับเรื่องแล้ว',
        ])->assertRedirect();

        $workflowRequest->refresh();

        $this->assertSame('in_review', $workflowRequest->status);
        $this->assertSame('รับเรื่องแล้ว', $workflowRequest->events()->latest()->first()?->comment);
    }

    public function test_manager_can_import_smartflow_csv_export(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP09999',
            'password' => 'password123',
        ]);

        $csv = implode("\n", [
            'document_number,workflow_id,workflow,title,details,requester_employee_code,status,priority,submitted_at,assigned_employee_code,assigned_group,smartflow_menu,legacy_reference,external_url,system,attachments',
            'SF-2606815,7,IT Helpdesk,VPN access,Need VPN for remote work,EMP00125,Accepted,High,2026-06-10 09:00:00,EMP00200,IT Helpdesk,Your Tasks,REF: #2606815,https://wdc.smartflow.pw/document/2606815/,VPN,https://example.com/smartflow.pdf',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'smartflow');
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'smartflow.csv', 'text/csv', null, true);

        $this->post(route('workflows.import'), [
            'smartflow_csv' => $file,
        ])->assertRedirect();

        $workflowRequest = WorkflowRequest::where('document_number', 'SF-2606815')->firstOrFail();

        $this->assertSame('accepted', $workflowRequest->status);
        $this->assertSame('smartflow', $workflowRequest->external_source);
        $this->assertSame('VPN', $workflowRequest->form_payload['system']);
        $this->assertSame(1, $workflowRequest->attachments()->count());
    }

    public function test_super_admin_can_update_workflow_template_backend(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();

        $this->post(route('login.store'), [
            'employee_code' => 'EMP09999',
            'password' => 'password123',
        ]);

        $this->patch(route('workflows.templates.update', $template), [
            'legacy_workflow_id' => '7',
            'name' => 'IT Helpdesk WDC',
            'category' => 'IT Helpdesk',
            'smartflow_menu' => 'tasks',
            'service_team' => 'IT Helpdesk',
            'description' => 'Updated from workflow backend',
            'form_schema_fields' => "System\nRequest type\nEvidence",
            'sla_hours' => 12,
            'approval_policy' => 'any_one',
            'legacy_url' => 'https://wdc.smartflow.pw/document/submit/7/',
            'step_lines' => "1|Accept Case|IT Helpdesk|รับเรื่อง|0\n2|Resolve Case|IT Helpdesk|แก้ไขและปิดงาน|1",
            'is_active' => '1',
        ])->assertRedirect();

        $template->refresh();

        $this->assertSame('IT Helpdesk WDC', $template->name);
        $this->assertSame(12, $template->sla_hours);
        $this->assertSame(['System', 'Request type', 'Evidence'], $template->schemaFields());
        $this->assertTrue($template->steps()->where('name', 'Resolve Case')->exists());
    }

    public function test_ticket_can_store_smartflow_request_type_and_reference(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->post(route('tickets.store'), [
            'title' => 'ขอใช้งาน VPN',
            'request_type' => 'vpn_access',
            'details' => 'ขอใช้งาน VPN สำหรับงานนอกสถานที่',
            'urgency' => 'normal',
            'legacy_document_ref' => 'REF: #2606815',
        ])->assertRedirect(route('workflows.index', ['template' => WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail()->id]));

        $ticket = WorkflowRequest::where('title', 'ขอใช้งาน VPN')->firstOrFail();

        $this->assertContains('vpn_access', $ticket->form_payload);
        $this->assertSame('REF: #2606815', $ticket->legacy_reference);
    }

    public function test_anonymous_complaint_does_not_store_reporter_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->post(route('complaints.store'), [
            'subject' => 'ทดสอบไม่เปิดเผยชื่อ',
            'details' => 'ต้องไม่เก็บ reporter_id และส่งถึง HR เสมอ',
        ])->assertRedirect(route('complaints.index'));

        $complaint = Complaint::where('subject', 'ทดสอบไม่เปิดเผยชื่อ')->firstOrFail();

        $this->assertTrue($complaint->is_anonymous);
        $this->assertNull($complaint->reporter_id);
        $this->assertSame('ร้องเรียน', $complaint->type);
        $this->assertSame('hr', $complaint->submitted_to);
    }

    public function test_it_user_can_manage_it_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00200',
            'password' => 'password123',
        ]);

        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('INVENTORY')
            ->assertSee('WDC-NB-0001');

        $this->post(route('assets.store'), [
            'code' => 'WDC-NB-TEST',
            'name' => 'Test Notebook',
            'status' => 'active',
            'department' => 'IT',
            'owner_name' => 'IT Test',
            'owner_id' => $employee->id,
            'price' => 25000,
        ])->assertRedirect();

        $asset = ItAsset::where('code', 'WDC-NB-TEST')->firstOrFail();

        $this->assertSame($employee->id, $asset->owner_id);
        $this->assertSame($employee->name, $asset->owner_name);

        $this->patch(route('assets.status', $asset), [
            'status' => 'repair',
            'notes' => 'Send to vendor',
        ])->assertRedirect();

        $this->assertSame('repair', $asset->fresh()->status);

        $this->actingAs($employee);

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('WDC-NB-TEST')
            ->assertSee('Test Notebook');

        $this->actingAs($itUser);

        $this->delete(route('assets.destroy', $asset))->assertRedirect();

        $this->assertDatabaseHas('it_assets', [
            'code' => 'WDC-NB-TEST',
            'status' => 'retired',
        ]);
        $this->assertDatabaseHas('asset_audit_logs', [
            'it_asset_id' => $asset->id,
            'action' => 'archive_asset',
        ]);
    }

    public function test_employee_without_asset_permission_cannot_open_assets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('assets.index'))->assertForbidden();
    }
}
