<?php

namespace Tests\Feature;

use App\Mail\PortalNotificationMail;
use App\Models\Complaint;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeDocument;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\ItAsset;
use App\Models\MeetingRoomBooking;
use App\Models\Permission;
use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\SoftwareLicense;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowAuthorization;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use App\Services\GoogleCalendarService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFake;
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
            ->assertDontSee('Approval Center')
            ->assertDontSee('Reports')
            ->assertDontSee(route('approvals.index'), false)
            ->assertDontSee(route('reports.index'), false)
            ->assertDontSee('search-box', false)
            ->assertDontSee('metric-grid', false)
            ->assertDontSee('ประกาศใหม่')
            ->assertDontSee('งาน IT ค้าง')
            ->assertDontSee('วิดีโอเทรนนิ่งใหม่')
            ->assertDontSee('ข้อมูลติดต่อ')
            ->assertDontSee('คำขอของฉัน')
            ->assertDontSee('คำขอ/อนุมัติของฉัน')
            ->assertDontSee('ระบบที่ใช้งานบ่อย')
            ->assertDontSee('ข่าวสาร HR IT และคู่มือ')
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

    public function test_employee_can_request_and_use_password_reset_by_employee_code(): void
    {
        $this->seed(DatabaseSeeder::class);
        NotificationFake::fake();

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $this->post(route('password.email'), [
            'account' => 'EMP00125',
        ])->assertSessionHas('status');

        $token = null;
        NotificationFake::assertSentTo($employee, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertNotEmpty($token);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $employee->email,
            'password' => 'NewWdc@2026',
            'password_confirmation' => 'NewWdc@2026',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewWdc@2026', $employee->fresh()->password));

        $this->followingRedirects()->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'NewWdc@2026',
        ])->assertOk();
    }

    public function test_password_reset_request_does_not_reveal_unknown_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        NotificationFake::fake();

        $this->post(route('password.email'), [
            'account' => 'UNKNOWN001',
        ])->assertSessionHas('status');

        NotificationFake::assertNothingSent();
    }

    public function test_admin_notification_page_shows_mail_readiness_without_secret_values(): void
    {
        $this->seed(DatabaseSeeder::class);

        config([
            'wdc.mail_notifications_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.zoho.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'tls',
            'mail.mailers.smtp.username' => 'notify@wdc.co.th',
            'mail.mailers.smtp.password' => 'super-secret-app-password',
            'mail.from.address' => 'notify@wdc.co.th',
        ]);

        $admin = User::where('employee_code', 'administrator')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.index', ['section' => 'notifications']))
            ->assertOk()
            ->assertSee('Zoho Mail / SMTP')
            ->assertSee(route('admin.mail-test'), false)
            ->assertSee('smtp')
            ->assertSee('smtp.zoho.com:587')
            ->assertSee('TLS')
            ->assertSee('notify@wdc.co.th')
            ->assertSee('พร้อมส่งอีเมล')
            ->assertDontSee('super-secret-app-password');
    }

    public function test_admin_mail_test_requires_ready_smtp_config(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        config([
            'wdc.mail_notifications_enabled' => false,
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => '127.0.0.1',
        ]);

        $admin = User::where('employee_code', 'administrator')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.mail-test'))
            ->assertRedirect(route('admin.index', ['section' => 'notifications']))
            ->assertSessionHasErrors('mail_test');

        Mail::assertNothingSent();
    }

    public function test_admin_can_send_mail_readiness_test_when_smtp_is_ready(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        config([
            'wdc.mail_notifications_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.zoho.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'tls',
            'mail.mailers.smtp.username' => 'notify@wdc.co.th',
            'mail.mailers.smtp.password' => 'super-secret-app-password',
            'mail.from.address' => 'notify@wdc.co.th',
        ]);

        $admin = User::where('employee_code', 'administrator')->firstOrFail();
        $admin->forceFill(['email' => 'administrator@wdc.co.th'])->save();

        $this->actingAs($admin)
            ->post(route('admin.mail-test'))
            ->assertRedirect(route('admin.index', ['section' => 'notifications']))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'mail_test',
            'title' => 'ทดสอบอีเมลจาก WDC Portal',
        ]);

        Mail::assertSent(PortalNotificationMail::class, fn (PortalNotificationMail $mail) => $mail->hasTo('administrator@wdc.co.th')
            && $mail->notification->title === 'ทดสอบอีเมลจาก WDC Portal');
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

    public function test_profile_external_links_redirect_only_when_real_urls_are_configured(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $this->actingAs($employee);

        config([
            'services.payroll.url' => 'https://example.com/payroll',
            'services.time_attendance.url' => null,
        ]);

        $this->get(route('payroll'))
            ->assertOk()
            ->assertSee('สลิปเงินเดือน');

        $this->get(route('time-attendance'))
            ->assertOk()
            ->assertSee('ลงเวลางาน');

        config([
            'services.payroll.url' => 'https://payroll.wdc.co.th/login',
            'services.time_attendance.url' => 'https://time.wdc.co.th/clock',
        ]);

        $this->get(route('payroll'))
            ->assertRedirect('https://payroll.wdc.co.th/login');

        $this->get(route('time-attendance'))
            ->assertRedirect('https://time.wdc.co.th/clock');
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
        Mail::fake();
        Storage::fake('local');
        config(['wdc.mail_notifications_enabled' => true]);
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

        $this->get(route('announcements.files.show', $file))
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=activity.png');

        Mail::assertSent(PortalNotificationMail::class, fn (PortalNotificationMail $mail) => $mail->notification->type === 'announcement'
            && $mail->notification->body === 'Activity Upload Test');
    }

    public function test_documents_download_real_uploaded_file_when_available(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        Storage::disk('local')->put('employee-documents/leave-form.txt', 'leave form source content');

        $document = EmployeeDocument::create([
            'employee_id' => null,
            'created_by' => $hr->id,
            'category' => 'HR/Leave',
            'title' => 'Leave Form',
            'file_name' => 'leave-form.txt',
            'file_path' => 'employee-documents/leave-form.txt',
            'mime_type' => 'text/plain',
            'summary' => 'Real downloadable form',
            'is_company_wide' => true,
        ]);

        $response = $this->actingAs($employee)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=leave-form.txt');

        $this->assertSame('leave form source content', $response->streamedContent());
    }

    public function test_hr_backend_uses_dashboard_and_section_menus(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $this->actingAs($hr);

        $this->get(route('hr.index'))
            ->assertOk()
            ->assertSee('hr-dashboard-summary', false)
            ->assertSee('hr-dashboard-grid', false)
            ->assertSee('แดชบอร์ด')
            ->assertSee('พนักงานทั้งหมด')
            ->assertSee('คำขอแก้โปรไฟล์')
            ->assertDontSee('ระบบที่ให้ IT เปิด')
            ->assertDontSee('เลขที่ประกาศ');

        $onboardingResponse = $this->get(route('hr.index', ['section' => 'onboarding']))
            ->assertOk()
            ->assertSee('ชื่อ ภาษาอังกฤษ')
            ->assertSee('นามสกุล ภาษาอังกฤษ')
            ->assertSee('ชื่อเล่น ภาษาไทย')
            ->assertSee('เบอร์โต๊ะ')
            ->assertSee('เลือกตำแหน่ง')
            ->assertSee('เลือกแผนก/BU')
            ->assertSee('bi-x-lg', false)
            ->assertSee(route('hr.index', ['section' => 'employees']), false)
            ->assertDontSee('ระบบที่ให้ IT เปิด')
            ->assertDontSee('เลขที่ประกาศ');
        $this->assertSame(1, substr_count($onboardingResponse->getContent(), 'bi-x-lg'));

        $this->get(route('hr.index', ['section' => 'announcements']))
            ->assertOk()
            ->assertSee('เลขที่ประกาศ')
            ->assertDontSee('ระบบที่ให้ IT เปิด');

        $employeesResponse = $this->get(route('hr.index', ['section' => 'employees']))
            ->assertOk()
            ->assertSee('รายชื่อพนักงาน')
            ->assertSee('Bundit Hirunyanitiwatna')
            ->assertSee('Chief Executive Officer')
            ->assertSee('Board Management')
            ->assertSee('<table', false)
            ->assertSee('bi-pencil-square', false)
            ->assertSee('รหัสพนักงาน')
            ->assertSee('วันที่เริ่มงาน')
            ->assertSee('ชื่ออังกฤษ')
            ->assertSee('ชื่อเล่นอังกฤษ')
            ->assertSee('ชื่อไทย')
            ->assertSee('ชื่อเล่นไทย')
            ->assertSee('ตำแหน่ง')
            ->assertSee('แผนก/BU')
            ->assertSee('ทีม')
            ->assertSee('สาขา')
            ->assertSee('อีเมล')
            ->assertSee('โทร')
            ->assertSee('เบอร์โต๊ะ')
            ->assertSee('สถานะ')
            ->assertSee('เพิ่มพนักงานใหม่')
            ->assertSee('คำขอแก้ข้อมูลโปรไฟล์')
            ->assertSee('ส่งออกข้อมูล')
            ->assertSee('Excel (.xls)')
            ->assertSee('CSV (.csv)')
            ->assertSee('hrEmployeeSearch', false)
            ->assertDontSee('แสดงรายชื่อพนักงานทั้งหมด')
            ->assertDontSee('administrator ·')
            ->assertDontSee('accountwdc@wdc.co.th')
            ->assertDontSee('Flagship Showroom')
            ->assertDontSee('เลขที่ประกาศ');
        $this->assertSame(1, substr_count($employeesResponse->getContent(), 'bi-person-plus'));
        $this->assertStringContainsString('btn btn-outline-primary', $employeesResponse->getContent());
        $this->assertSame(1, substr_count($employeesResponse->getContent(), 'bi-person-gear'));
        $this->assertLessThan(
            strpos($employeesResponse->getContent(), 'bi-download'),
            strpos($employeesResponse->getContent(), 'bi-person-gear'),
        );

        $csvExport = $this->get(route('hr.employees.export', ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csvExport->streamedContent();

        $this->assertStringContainsString('Bundit Hirunyanitiwatna', $csvContent);
        $this->assertStringNotContainsString('accountwdc@wdc.co.th', $csvContent);
        $this->assertStringNotContainsString('Flagship Showroom', $csvContent);
        $this->assertStringContainsString('วันที่เริ่มงาน', $csvContent);
        $this->assertStringContainsString('ชื่อเล่นไทย', $csvContent);
        $this->assertStringNotContainsString('administrator', $csvContent);

        $excelExport = $this->get(route('hr.employees.export', ['format' => 'xls']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $excelContent = $excelExport->streamedContent();

        $this->assertStringContainsString('Bundit Hirunyanitiwatna', $excelContent);
        $this->assertStringNotContainsString('accountwdc@wdc.co.th', $excelContent);
        $this->assertStringNotContainsString('Flagship Showroom', $excelContent);
        $this->assertStringContainsString('วันที่เริ่มงาน', $excelContent);
        $this->assertStringContainsString('ชื่อเล่นไทย', $excelContent);
        $this->assertStringContainsString('<table', $excelContent);

        $this->get(route('hr.index', ['section' => 'employees', 'employee_q' => 'Aiyada']))
            ->assertOk()
            ->assertSee('Aiyada Supso')
            ->assertDontSee('Alisa Kerdphokha')
            ->assertDontSee('Flagship Showroom');

        $filteredCsv = $this->get(route('hr.employees.export', ['format' => 'csv', 'employee_q' => 'Aiyada']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Aiyada Supso', $filteredCsv);
        $this->assertStringNotContainsString('Alisa Kerdphokha', $filteredCsv);
    }

    public function test_hr_can_update_directory_employee_from_employee_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $entry = EmployeeDirectoryEntry::where('entry_type', 'employee')
            ->where('display_name', 'Bundit Hirunyanitiwatna')
            ->firstOrFail();

        $this->actingAs($hr);

        $this->patch(route('hr.directory-entries.update', $entry), [
            'employee_code' => '8000',
            'start_date' => '2026-06-01',
            'english_name' => 'Bundit Hirunyanitiwatna',
            'english_nickname' => 'Bank',
            'thai_name' => 'บัณฑิต หิรัญญนิธิวัฒนา',
            'thai_nickname' => 'แบ้งค์',
            'position' => 'Chief Executive Officer',
            'department' => 'Board Management',
            'team' => 'Executive',
            'location' => 'Lumpini',
            'email' => 'bundit.hi@wdc.co.th',
            'phone' => '0800000000',
            'extension_number' => '8000',
            'employment_status' => 'active',
        ])->assertRedirect();

        $entry->refresh();

        $this->assertSame('8000', $entry->employeeCode());
        $this->assertSame('2026-06-01', $entry->startDate()->toDateString());
        $this->assertSame('Bank', $entry->englishNickname());
        $this->assertSame('Executive', $entry->team);
        $this->assertSame('bundit.hi@wdc.co.th', $entry->email);
        $this->assertTrue($entry->is_active);
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
            ->assertSee('ค้นหาสมาชิก')
            ->assertSee('ข้อมูลพนักงานสำหรับหน้ารายชื่อ')
            ->assertSee('ปรับสิทธิ์รายคน')
            ->assertSee('สิทธิ์มาตรฐานจาก Role')
            ->assertSee('ตาม Role')
            ->assertSee('data-permission-editor', false)
            ->assertSee('data-role-permissions', false)
            ->assertSee('data-role-baseline', false)
            ->assertSee('data-bs-target="#employee-profile-', false)
            ->assertSee('data-bs-target="#employee-permissions-', false)
            ->assertSee(route('admin.directory-users.sync'), false)
            ->assertSee('ใช้งาน')
            ->assertSee('EMP00125')
            ->assertDontSee('administrator ·')
            ->assertDontSee('EMP09999 ·');

        $this->get(route('admin.index', ['section' => 'permissions', 'q' => 'somchai']))
            ->assertOk()
            ->assertSee('EMP00125')
            ->assertDontSee('EMP00200');

        $this->get(route('admin.index', ['section' => 'role-template']))
            ->assertOk()
            ->assertSee('Role Template');

        $this->get(route('admin.index', ['section' => 'activity-logs']))
            ->assertOk()
            ->assertSee('Activity Logs');

        $this->get(route('admin.index', ['section' => 'users']))
            ->assertOk()
            ->assertSee('เพิ่มผู้ใช้งาน')
            ->assertDontSee('เมนูด้านซ้ายหน้าบ้าน');

        $this->get(route('admin.index', ['section' => 'roles']))
            ->assertOk()
            ->assertSee('Role Template')
            ->assertDontSee('เมนูด้านซ้ายหน้าบ้าน');

        $this->get(route('admin.index', ['section' => 'activity']))
            ->assertOk()
            ->assertSee('Activity Logs')
            ->assertDontSee('เมนูด้านซ้ายหน้าบ้าน');
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
        $auditorRole = Role::where('slug', 'auditor')->with('permissions')->firstOrFail();
        $itSupportRole = Role::where('slug', 'it_support')->with('permissions')->firstOrFail();
        $itSupervisorRole = Role::where('slug', 'it_supervisor')->with('permissions')->firstOrFail();

        $this->assertSame([
            'employee',
            'hr',
            'it_supervisor',
            'it_support',
            'admin',
            'super_admin',
            'auditor',
        ], Role::orderByRaw("CASE slug
            WHEN 'employee' THEN 10
            WHEN 'hr' THEN 20
            WHEN 'it_supervisor' THEN 30
            WHEN 'it_support' THEN 40
            WHEN 'admin' THEN 50
            WHEN 'super_admin' THEN 60
            WHEN 'auditor' THEN 70
            ELSE 999
        END")->pluck('slug')->all());

        $this->assertDatabaseMissing('roles', ['slug' => 'supervisor']);
        $this->assertDatabaseMissing('roles', ['slug' => 'iam_admin']);
        $this->assertDatabaseMissing('roles', ['slug' => 'it_asset_officer']);
        $this->assertDatabaseMissing('roles', ['slug' => 'it_asset_admin']);

        $this->assertTrue($hrRole->permissions->contains('key', 'directory.manage'));
        $this->assertTrue($hrRole->permissions->contains('key', 'hr.onboarding.manage'));
        $this->assertTrue($hrRole->permissions->contains('key', 'documents.manage'));
        $this->assertTrue($hrRole->permissions->contains('key', 'complaints.review'));
        $this->assertTrue($adminRole->permissions->contains('key', 'assets.settings.manage'));
        $this->assertTrue($adminRole->permissions->contains('key', 'assets.delete'));
        $this->assertTrue($adminRole->permissions->contains('key', 'iam.users.manage'));
        $this->assertTrue($adminRole->permissions->contains('key', 'iam.roles.manage'));
        $this->assertTrue($adminRole->permissions->contains('key', 'admin.system.manage'));
        $this->assertTrue($auditorRole->permissions->contains('key', 'audit.logs.view'));
        $this->assertTrue($auditorRole->permissions->contains('key', 'audit.logs.export'));
        $this->assertFalse($auditorRole->permissions->contains('key', 'admin.users.manage'));
        $this->assertTrue($itSupportRole->permissions->contains('key', 'assets.manage'));
        $this->assertTrue($itSupportRole->permissions->contains('key', 'it.onboarding.manage'));
        $this->assertFalse($itSupportRole->permissions->contains('key', 'assets.delete'));
        $this->assertTrue($itSupervisorRole->permissions->contains('key', 'assets.settings.manage'));
        $this->assertTrue($itSupervisorRole->permissions->contains('key', 'assets.delete'));
        $this->assertTrue($itSupervisorRole->permissions->contains('key', 'it.onboarding.manage'));
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
        Mail::fake();
        config(['wdc.mail_notifications_enabled' => true]);
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
        $this->assertCount(5, $onboarding->systems);
        $this->assertEqualsCanonicalizing(
            ['WDC Portal', 'Active Directory', 'EMAIL', 'ทรัพย์สิน', 'ERP'],
            $onboarding->systems->pluck('system_name')->all(),
        );
        $this->assertDatabaseHas('employee_onboarding_systems', [
            'employee_onboarding_request_id' => $onboarding->id,
            'system_name' => 'WDC Portal',
            'username' => 'EMP77777',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $administrator->id,
            'type' => 'onboarding',
            'title' => 'มีรายการพนักงานใหม่จาก HR',
            'url' => route('onboarding.show', $onboarding),
        ]);
        Mail::assertSent(PortalNotificationMail::class, fn (PortalNotificationMail $mail) => $mail->hasTo($itUser->email)
            && $mail->notification->title === 'มีรายการพนักงานใหม่จาก HR'
            && $mail->notification->url === route('onboarding.show', $onboarding));

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
            ->assertSee('Active Directory')
            ->assertSee('EMAIL')
            ->assertSee('ทรัพย์สิน')
            ->assertSee('รหัสเข้าใช้งาน WDC Portal ล็อกตามรหัสพนักงาน')
            ->assertSee('รับงาน');

        $emailSystem = $onboarding->systems->firstWhere('system_name', 'EMAIL');
        $assetSystem = $onboarding->systems->firstWhere('system_name', 'ทรัพย์สิน');

        $this->actingAs($itUser);

        $this->patch(route('it.onboarding.claim', $onboarding))->assertRedirect();

        $this->get(route('onboarding.show', $onboarding))
            ->assertOk()
            ->assertSee('รายการเปิดระบบโดย IT')
            ->assertSee('รับงานโดย')
            ->assertSee('รหัสเข้าใช้งาน WDC')
            ->assertSee('Domain / Email อ้างอิง')
            ->assertSee('เลือกทรัพย์สิน')
            ->assertSee('บันทึกข้อมูล IT')
            ->assertSee('อนุมัติเปิดระบบและส่งกลับ HR');

        $this->patch(route('it.onboarding.update', $onboarding), [
            'systems' => [
                $emailSystem->id => [
                    'status' => 'provisioned',
                    'username' => 'new.starter',
                    'email' => 'new.starter@wdc.co.th',
                    'notes' => 'เปิดอีเมลแล้ว',
                ],
                $assetSystem->id => [
                    'status' => 'provisioned',
                    'it_asset_id' => $asset->id,
                    'notes' => 'มอบเครื่องแล้ว',
                ],
            ],
            'it_note' => 'พร้อมให้ HR ตรวจสอบ',
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_onboarding_systems', [
            'employee_onboarding_request_id' => $onboarding->id,
            'system_name' => 'WDC Portal',
            'username' => 'EMP77777',
        ]);
        $this->assertDatabaseHas('employee_onboarding_requests', [
            'id' => $onboarding->id,
            'claimed_by_id' => $itUser->id,
        ]);
        $this->assertDatabaseHas('employee_onboarding_systems', [
            'id' => $emailSystem->id,
            'status' => 'provisioned',
            'provisioned_by_id' => $itUser->id,
        ]);

        $export = $this->get(route('it.onboarding.export', ['format' => 'csv']));

        $export->assertOk();
        $exportContent = $export->streamedContent();

        $this->assertStringContainsString('Staff ID', $exportContent);
        $this->assertStringContainsString('EMP77777', $exportContent);
        $this->assertStringContainsString('E-Mail by', $exportContent);
        $this->assertStringContainsString($itUser->name, $exportContent);

        $this->get(route('it.index'))
            ->assertOk()
            ->assertSee('onboarding-system-summary', false)
            ->assertSee('onboarding-inline-details', false)
            ->assertSee('it-access-registry')
            ->assertSee('it-access-table')
            ->assertSee('EMP77777')
            ->assertSee('new.starter')
            ->assertSee('new.starter@wdc.co.th')
            ->assertSee($asset->code)
            ->assertSee($itUser->name);

        $this->patch(route('it.onboarding.complete', $onboarding))->assertRedirect();

        $this->assertSame('it_completed', $onboarding->fresh()->status);

        $this->actingAs($hr);
        $displayDate = now()->addDay()->toDateString();

        $this->patch(route('hr.onboarding.publish', $onboarding), [
            'photo' => UploadedFile::fake()->image('new-starter.jpg', 640, 640),
            'hr_note' => 'อนุมัติแสดงรายชื่อ',
            'published_at' => $displayDate,
        ])->assertRedirect();

        $onboarding->refresh();
        $createdUser = User::where('employee_code', 'EMP77777')->firstOrFail();
        $directoryEntry = EmployeeDirectoryEntry::where('source_system', 'wdc')
            ->where('source_record_id', 'EMP77777')
            ->firstOrFail();

        $this->assertSame('hr_approved', $onboarding->status);
        $this->assertSame('New Starter', $createdUser->name);
        $this->assertSame($displayDate, $directoryEntry->published_at->toDateString());
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

        $this->get(route('hr.index', ['section' => 'employees']))
            ->assertOk()
            ->assertSee('employee-registry-table')
            ->assertDontSee('EMP77777')
            ->assertDontSee('New Starter')
            ->assertDontSee('new.starter@wdc.co.th');

        $this->get(route('directory.index', ['q' => 'New Starter']))
            ->assertOk()
            ->assertDontSee('New Starter (New)');

        $this->get(route('search', ['q' => 'New Starter']))
            ->assertOk()
            ->assertDontSee('<strong>New Starter</strong>', false);

        $this->travelTo($directoryEntry->published_at->copy()->addMinute());

        $this->get(route('hr.index', ['section' => 'employees']))
            ->assertOk()
            ->assertSee('employee-registry-table')
            ->assertSee('EMP77777')
            ->assertSee('New Starter')
            ->assertSee('new.starter@wdc.co.th');

        $this->get(route('directory.index', ['q' => 'New Starter']))
            ->assertOk()
            ->assertSee('New Starter (New)')
            ->assertSee('พนักงาน ใหม่ (ใหม่)');

        $this->get(route('search', ['q' => 'New Starter']))
            ->assertOk()
            ->assertSee('<strong>New Starter</strong>', false);
    }

    public function test_hr_can_cancel_onboarding_before_it_starts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();

        $this->actingAs($hr);

        $this->post(route('hr.onboarding.store'), [
            'employee_code' => 'EMP88001',
            'english_first_name' => 'Cancel',
            'english_last_name' => 'Beforeit',
            'thai_first_name' => 'ทดสอบ',
            'thai_last_name' => 'ยกเลิก',
            'english_nickname' => 'Can',
            'thai_nickname' => 'แคน',
            'department_id' => $hr->employee->department_id,
            'position' => 'IT Support',
            'team' => 'Team A',
            'location' => 'Lumpini',
            'corporate_email' => 'cancel.beforeit@wdc.co.th',
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

        $onboarding = EmployeeOnboardingRequest::where('employee_code', 'EMP88001')->firstOrFail();

        $this->patch(route('hr.onboarding.cancel', $onboarding), [
            'cancel_reason' => 'พนักงานแจ้งว่าไม่มาเริ่มงาน',
        ])->assertRedirect();

        $onboarding->refresh();

        $this->assertSame('cancelled', $onboarding->status);
        $this->assertSame($hr->id, $onboarding->cancel_requested_by);
        $this->assertSame($hr->id, $onboarding->cancel_confirmed_by);
        $this->assertNotNull($onboarding->cancelled_at);
        $this->assertDatabaseMissing('users', ['employee_code' => 'EMP88001']);
    }

    public function test_hr_cancel_onboarding_after_it_started_requires_it_confirmation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();

        $this->actingAs($hr);

        $this->post(route('hr.onboarding.store'), [
            'employee_code' => 'EMP88002',
            'english_first_name' => 'Cancel',
            'english_last_name' => 'Afterit',
            'thai_first_name' => 'ทดสอบ',
            'thai_last_name' => 'ไอที',
            'english_nickname' => 'Cat',
            'thai_nickname' => 'แคท',
            'department_id' => $hr->employee->department_id,
            'position' => 'IT Support',
            'team' => 'Team A',
            'location' => 'Lumpini',
            'corporate_email' => 'cancel.afterit@wdc.co.th',
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

        $onboarding = EmployeeOnboardingRequest::where('employee_code', 'EMP88002')->firstOrFail();

        $this->actingAs($itUser);
        $this->patch(route('it.onboarding.claim', $onboarding))->assertRedirect();

        $this->actingAs($hr);
        $this->patch(route('hr.onboarding.cancel', $onboarding), [
            'cancel_reason' => 'พนักงานขอเลื่อนแบบไม่มีกำหนด',
            'cancel_acknowledged' => '1',
        ])->assertRedirect();

        $onboarding->refresh();

        $this->assertSame('cancel_requested', $onboarding->status);
        $this->assertSame($hr->id, $onboarding->cancel_requested_by);
        $this->assertNull($onboarding->cancelled_at);

        $this->actingAs($itUser);

        $this->patch(route('it.onboarding.cancel', $onboarding), [
            'it_note' => 'ตรวจสอบแล้ว ยังไม่มีระบบที่ต้องคืน',
        ])->assertRedirect();

        $onboarding->refresh();

        $this->assertSame('cancelled', $onboarding->status);
        $this->assertSame($itUser->id, $onboarding->cancel_confirmed_by);
        $this->assertNotNull($onboarding->cancelled_at);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $hr->id,
            'type' => 'onboarding',
            'title' => 'IT ตรวจสอบและยืนยันการยกเลิกแล้ว',
        ]);
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
            ->assertSee('meeting-sync-grid', false)
            ->assertSee('MEETING_ROOM_GOOGLE_SERVICE_ACCOUNT_JSON')
            ->assertSee('MEETING_ROOM_GOOGLE_CALENDAR_ID')
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

    public function test_hr_can_upload_and_delete_company_form_document(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $this->actingAs($hr);

        $this->post(route('documents.store'), [
            'department' => 'HR',
            'topic' => 'Sick Leave',
            'title' => 'Sick Leave Form',
            'summary' => 'Form for HR leave request',
            'file' => UploadedFile::fake()->create('sick-leave.pdf', 24, 'application/pdf'),
        ])->assertRedirect(route('documents.index', ['department' => 'HR']));

        $document = EmployeeDocument::where('file_name', 'sick-leave.pdf')->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('HR/Sick Leave', $document->category);
        $this->assertTrue($document->is_company_wide);

        $this->get(route('documents.index', ['department' => 'HR']))
            ->assertOk()
            ->assertSee('sick-leave.pdf')
            ->assertSee(route('documents.destroy', $document), false);

        $this->delete(route('documents.destroy', $document))->assertRedirect();

        Storage::disk('local')->assertMissing($document->file_path);
        $this->assertDatabaseMissing('employee_documents', ['id' => $document->id]);
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
            ->assertSee(route('directory.index', ['type' => 'employee']), false)
            ->assertSee(route('directory.index', ['type' => 'mail_group']), false)
            ->assertSee(route('directory.index', ['type' => 'showroom']), false)
            ->assertSee(route('directory.index', ['type' => 'resigned']), false)
            ->assertSee('Group Mail')
            ->assertDontSee('role-badge', false)
            ->assertDontSee('ข้อมูลทั้งหมด')
            ->assertDontSee('นำเข้าจาก Notion')
            ->assertDontSee('อัปเดตล่าสุด')
            ->assertSee('รหัสพนักงาน')
            ->assertSee('เบอร์โทรโต๊ะ')
            ->assertDontSee('ข้อมูลต้นทาง')
            ->assertDontSee('class="tag"', false)
            ->assertDontSee('เพิ่มข้อมูลรายชื่อ')
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
            ->assertDontSee('Concept Store')
            ->assertDontSee('accountwdc@wdc.co.th');
    }

    public function test_directory_resigned_filter_is_separate_from_active_employees(): void
    {
        $this->seed(DatabaseSeeder::class);

        EmployeeDirectoryEntry::create([
            'source_system' => 'manual',
            'source_record_id' => 'resigned-member',
            'entry_type' => 'employee',
            'employment_status' => 'resigned',
            'display_name' => 'Former WDC Member',
            'english_name' => 'Former WDC Member',
            'thai_name' => 'อดีต พนักงาน',
            'department' => 'Sales',
            'position' => 'Sales Officer',
            'location' => 'Lumpini',
            'email' => 'former.member@wdc.co.th',
            'is_active' => false,
            'resigned_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index', ['type' => 'resigned']))
            ->assertOk()
            ->assertSee('directory-quick-link active', false)
            ->assertSee('Former WDC Member')
            ->assertDontSee('Bundit Hirunyanitiwatna');
    }

    public function test_directory_employee_filter_without_search_shows_all_active_employees_with_new_hires_first(): void
    {
        $this->seed(DatabaseSeeder::class);

        EmployeeDirectoryEntry::create([
            'source_system' => 'manual',
            'source_record_id' => 'active-employee-no-filter',
            'entry_type' => 'employee',
            'employment_status' => 'active',
            'display_name' => 'Aaa Active Employee',
            'english_name' => 'Aaa Active Employee',
            'department' => '000 Operations',
            'position' => 'Operations Officer',
            'location' => 'Lumpini',
            'email' => 'active.employee@wdc.co.th',
            'is_active' => true,
            'published_at' => now(),
        ]);

        EmployeeDirectoryEntry::create([
            'source_system' => 'manual',
            'source_record_id' => 'new-employee-no-filter',
            'entry_type' => 'employee',
            'employment_status' => 'active',
            'display_name' => 'Aaa New Employee',
            'english_name' => 'Aaa New Employee',
            'department' => '000 Operations',
            'position' => 'Operations Officer',
            'location' => 'Lumpini',
            'email' => 'new.employee@wdc.co.th',
            'raw_payload' => [
                'start_date' => now()->toDateString(),
                'employee_code' => 'EMPNEW01',
            ],
            'is_active' => true,
            'published_at' => now(),
        ]);

        EmployeeDirectoryEntry::create([
            'source_system' => 'manual',
            'source_record_id' => 'mail-group-not-employee-filter',
            'entry_type' => 'mail_group',
            'display_name' => 'no_filter_group@wdc.co.th',
            'email' => 'no_filter_group@wdc.co.th',
            'department' => 'Mail Group',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee('directory-quick-link active', false)
            ->assertSeeInOrder(['Aaa New Employee', 'Aaa Active Employee'])
            ->assertDontSee('no_filter_group@wdc.co.th')
            ->assertDontSee('Flagship Showroom');
    }

    public function test_it_can_create_directory_group_mail_and_showroom_entries(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Role::where('slug', 'it_supervisor')->firstOrFail()->permissions->contains('key', 'directory.manage'));
        $this->assertTrue(Role::where('slug', 'it_support')->firstOrFail()->permissions->contains('key', 'directory.manage'));

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00200',
            'password' => 'password123',
        ]);

        $this->post(route('directory.store'), [
            'entry_type' => 'mail_group',
            'display_name' => 'support_group@wdc.co.th',
            'email' => 'support_group@wdc.co.th',
            'department' => 'IT',
            'location' => 'Lumpini',
        ])->assertRedirect(route('directory.index', ['type' => 'mail_group']));

        $this->post(route('directory.store'), [
            'entry_type' => 'showroom',
            'display_name' => 'Rayong Showroom',
            'phone' => '038-000-000',
            'location' => 'Rayong',
        ])->assertRedirect(route('directory.index', ['type' => 'showroom']));

        $this->assertDatabaseHas('employee_directory_entries', [
            'source_system' => 'wdc_manual',
            'entry_type' => 'mail_group',
            'display_name' => 'support_group@wdc.co.th',
        ]);

        $this->assertDatabaseHas('employee_directory_entries', [
            'source_system' => 'wdc_manual',
            'entry_type' => 'showroom',
            'display_name' => 'Rayong Showroom',
            'department' => 'Showroom',
        ]);

        $this->get(route('directory.index', ['type' => 'mail_group']))
            ->assertOk()
            ->assertSee('support_group@wdc.co.th');

        $this->get(route('directory.index', ['type' => 'showroom']))
            ->assertOk()
            ->assertSee('Rayong Showroom')
            ->assertSee('038-000-000');
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
        Mail::fake();
        config(['wdc.mail_notifications_enabled' => true]);

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
        Mail::assertSent(PortalNotificationMail::class, fn (PortalNotificationMail $mail) => $mail->notification->type === 'workflow');
    }

    public function test_workflow_request_can_store_and_download_uploaded_attachment(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Mail::fake();

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->post(route('workflows.store'), [
            'workflow_template_id' => $template->id,
            'title' => 'Upload evidence from WDC',
            'details' => 'Attach local evidence instead of only a SmartFlow URL.',
            'priority' => 'normal',
            'workflow_files' => [
                UploadedFile::fake()->create('smartflow-evidence.pdf', 128, 'application/pdf'),
            ],
        ])->assertRedirect(route('workflows.index'));

        $workflowRequest = WorkflowRequest::where('title', 'Upload evidence from WDC')->firstOrFail();
        $attachment = $workflowRequest->attachments()->firstOrFail();

        $this->assertSame('smartflow-evidence.pdf', $attachment->file_name);
        $this->assertNotNull($attachment->file_path);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->get(route('workflows.attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('smartflow-evidence.pdf');
    }

    public function test_workflow_index_can_search_and_filter_smartflow_documents(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();
        $otherTemplate = WorkflowTemplate::where('id', '!=', $template->id)->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $employee->id,
            'document_number' => 'WDC-SF-SEARCH-00001',
            'title' => 'VPN access searchable request',
            'details' => 'Need VPN for remote work.',
            'priority' => 'normal',
            'status' => 'submitted',
            'legacy_reference' => 'REF: #SEARCHVPN',
            'submitted_at' => now(),
        ]);

        WorkflowRequest::create([
            'workflow_template_id' => $otherTemplate->id,
            'requester_id' => $employee->id,
            'document_number' => 'WDC-SF-HIDDEN-00002',
            'title' => 'Hidden marketing request',
            'details' => 'Should not appear in filtered result.',
            'priority' => 'normal',
            'status' => 'completed',
            'submitted_at' => now(),
        ]);

        $oldWorkflowRequest = WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $employee->id,
            'document_number' => 'WDC-SF-OLD-00003',
            'title' => 'Old VPN archive request',
            'details' => 'Should not appear when filtering from today.',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now()->subDays(10),
        ]);
        $oldWorkflowRequest->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index', [
            'q' => 'SEARCHVPN',
            'template' => $template->id,
            'status' => 'submitted',
        ]))
            ->assertOk()
            ->assertSee('VPN access searchable request')
            ->assertDontSee('Hidden marketing request');

        $this->get(route('workflows.index', [
            'date_from' => now()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('VPN access searchable request')
            ->assertDontSee('Old VPN archive request');
    }

    public function test_workflow_work_center_shows_smartflow_navigation_and_document_summary(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $employee->id,
            'document_number' => 'WDC-SF-NAV-00001',
            'title' => 'SmartFlow navigation parity',
            'details' => 'Verify command bar and document summary in WDC.',
            'priority' => 'high',
            'status' => 'submitted',
            'submitted_at' => now(),
            'due_at' => now()->addDay(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index'))
            ->assertOk()
            ->assertSee('smartflow-command-bar', false)
            ->assertSee('New Document')
            ->assertSee('Your Tasks')
            ->assertSee('All Documents')
            ->assertSee('Diagrams')
            ->assertSee('Favorites')
            ->assertSee('smartflow-diagrams', false)
            ->assertSee('Show Advanced Filters')
            ->assertSee('workflow-create-form', false)
            ->assertSee('smartflow-document-card', false)
            ->assertSee('smartflow-document-summary', false)
            ->assertSee('Document No.')
            ->assertSee('REF:')
            ->assertSee('Flow:')
            ->assertSee('Step:')
            ->assertSee('Current Step')
            ->assertSee('WDC-SF-NAV-00001')
            ->assertSee('SmartFlow navigation parity');
    }

    public function test_user_can_create_and_revoke_smartflow_approval_authorization(): void
    {
        $this->seed(DatabaseSeeder::class);

        $manager = User::where('employee_code', 'EMP00200')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $this->post(route('login.store'), [
            'employee_code' => $manager->employee_code,
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index', ['view' => 'authorizations']))
            ->assertOk()
            ->assertSee('Approval Authorizations')
            ->assertSee("Authorizations You've Given", false)
            ->assertSee('Authorizations Given To You')
            ->assertSee('Create Authorization');

        $this->post(route('workflows.authorizations.store'), [
            'authorized_user_id' => $employee->id,
            'valid_from' => now()->subHour()->format('Y-m-d\TH:i'),
            'valid_until' => now()->addDay()->format('Y-m-d\TH:i'),
            'reason' => 'Vacation coverage',
        ])->assertRedirect(route('workflows.index', ['view' => 'authorizations']));

        $authorization = WorkflowAuthorization::where('authorizer_id', $manager->id)
            ->where('authorized_user_id', $employee->id)
            ->firstOrFail();

        $this->assertSame('active', $authorization->status);

        $this->delete(route('workflows.authorizations.revoke', $authorization))->assertRedirect(route('workflows.index', ['view' => 'authorizations']));

        $this->assertSame('revoked', $authorization->fresh()->status);
    }

    public function test_authorized_delegate_can_see_and_update_assigned_workflow_task(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();
        $manager = User::where('employee_code', 'EMP00200')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        WorkflowAuthorization::create([
            'authorizer_id' => $manager->id,
            'authorized_user_id' => $employee->id,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
            'reason' => 'Approval backup',
            'status' => 'active',
        ]);

        $workflowRequest = WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $manager->id,
            'assigned_to' => $manager->id,
            'current_step_id' => $template->steps()->first()?->id,
            'document_number' => 'WDC-SF-DELEGATE-00001',
            'title' => 'Delegated SmartFlow approval',
            'details' => 'Assigned to manager but visible to delegated user.',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => $employee->employee_code,
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index', ['view' => 'tasks']))
            ->assertOk()
            ->assertSee('Delegated SmartFlow approval')
            ->assertSee('WDC-SF-DELEGATE-00001');

        $this->patch(route('workflows.status', $workflowRequest), [
            'status' => 'in_review',
            'comment' => 'Approved by delegated user',
        ])->assertRedirect();

        $this->assertSame('in_review', $workflowRequest->fresh()->status);
        $this->assertSame('Approved by delegated user', $workflowRequest->events()->latest()->first()?->comment);
    }

    public function test_workflow_statistics_view_matches_smartflow_statistics_sections(): void
    {
        $this->seed(DatabaseSeeder::class);

        $template = WorkflowTemplate::where('name', 'IT Helpdesk')->firstOrFail();
        $manager = User::where('employee_code', 'EMP00200')->firstOrFail();

        $completedRequest = WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $manager->id,
            'assigned_to' => $manager->id,
            'document_number' => 'WDC-SF-STATS-00001',
            'title' => 'Completed statistics request',
            'details' => 'Completed request for statistics.',
            'priority' => 'normal',
            'status' => 'completed',
            'submitted_at' => now()->subHours(3),
            'completed_at' => now()->subHour(),
        ]);

        $completedRequest->events()->create([
            'user_id' => $manager->id,
            'action' => 'status_change',
            'from_status' => 'in_progress',
            'to_status' => 'completed',
            'comment' => 'Closed for statistics.',
        ]);

        WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => $manager->id,
            'assigned_to' => $manager->id,
            'document_number' => 'WDC-SF-STATS-00002',
            'title' => 'Pending statistics request',
            'details' => 'Pending request for statistics.',
            'priority' => 'normal',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'employee_code' => $manager->employee_code,
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index', ['view' => 'statistics']))
            ->assertOk()
            ->assertSee('User Statistics')
            ->assertSee('Workflow Statistics')
            ->assertSee('Total Decisions')
            ->assertSee('Pending Approvals')
            ->assertSee('Avg. Response')
            ->assertSee('Avg. Completion Time')
            ->assertSee($manager->name)
            ->assertSee('IT Helpdesk');
    }

    public function test_workflow_dynamic_fields_view_lists_smartflow_field_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP09999',
            'password' => 'password123',
        ]);

        $this->get(route('workflows.index', ['view' => 'dynamic_fields']))
            ->assertOk()
            ->assertSee('Dynamic Fields')
            ->assertSee('Configure workflow custom fields')
            ->assertSee('Create New Field')
            ->assertSee('IT Helpdesk')
            ->assertSee('Checkbox')
            ->assertSee('Preview')
            ->assertSee('Edit')
            ->assertSee('Delete');
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
        Mail::fake();
        config(['wdc.mail_notifications_enabled' => true]);

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
        Mail::assertSent(PortalNotificationMail::class, fn (PortalNotificationMail $mail) => $mail->notification->type === 'workflow'
            && $mail->hasTo($manager->email));
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
            ->assertSee('WDC-NB-0001')
            ->assertSee('asset-action-details', false)
            ->assertSee('id="new-asset"', false)
            ->assertSee('Software License');

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

    public function test_hr_it_offboarding_flow_disables_employee_and_releases_asset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $asset = ItAsset::where('code', 'WDC-NB-0001')->firstOrFail();
        $asset->update([
            'owner_id' => $employee->id,
            'owner_name' => $employee->name,
        ]);

        $this->actingAs($hr);

        $this->post(route('hr.offboarding.store'), [
            'employee_user_id' => $employee->id,
            'resignation_date' => now()->toDateString(),
            'hr_note' => 'ปิดระบบหลังวันสุดท้าย',
        ])->assertRedirect();

        $offboarding = EmployeeOffboardingRequest::with('systems')->where('employee_user_id', $employee->id)->firstOrFail();

        $this->assertSame('pending_it', $offboarding->status);
        $this->assertTrue($offboarding->systems->contains('system_name', 'WDC Portal'));
        $this->assertTrue($offboarding->systems->contains('system_name', "คืนทรัพย์สิน: {$asset->code}"));

        $this->get(route('hr.index', ['section' => 'offboarding']))
            ->assertOk()
            ->assertSee('แจ้งพนักงานลาออก')
            ->assertSee($employee->employee_code);

        $this->actingAs($itUser);

        $this->patch(route('it.offboarding.claim', $offboarding))->assertRedirect();

        $offboarding->refresh()->load('systems');
        $assetSystem = $offboarding->systems->firstWhere('it_asset_id', $asset->id);
        $portalSystem = $offboarding->systems->firstWhere('system_name', 'WDC Portal');

        $this->get(route('offboarding.show', $offboarding))
            ->assertOk()
            ->assertSee('รายการปิดระบบโดย IT')
            ->assertSee('รับงานโดย')
            ->assertSee('ปิดระบบเรียบร้อย แจ้ง HR');

        $this->patch(route('it.offboarding.update', $offboarding), [
            'systems' => [
                $portalSystem->id => [
                    'status' => 'completed',
                    'notes' => 'ปิด WDC แล้ว',
                ],
                $assetSystem->id => [
                    'status' => 'completed',
                    'notes' => 'รับคืนเครื่องแล้ว',
                ],
            ],
            'it_note' => 'ปิดระบบหลักและรับคืนทรัพย์สินแล้ว',
        ])->assertRedirect();

        $this->assertDatabaseHas('employee_offboarding_systems', [
            'id' => $portalSystem->id,
            'status' => 'completed',
            'completed_by_id' => $itUser->id,
        ]);

        $this->patch(route('it.offboarding.complete', $offboarding))->assertRedirect();
        $this->assertSame('it_completed', $offboarding->fresh()->status);
        $this->assertNull($asset->fresh()->owner_id);

        $this->actingAs($hr);

        $this->patch(route('hr.offboarding.approve', $offboarding))->assertRedirect();

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertSame('hr_approved', $offboarding->fresh()->status);
        $this->assertDatabaseHas('employee_directory_entries', [
            'source_system' => 'wdc',
            'source_record_id' => $employee->employee_code,
            'employment_status' => 'resigned',
            'is_active' => false,
        ]);
    }

    public function test_approval_center_collects_pending_work_for_authorized_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $department = $hr->employee->department;

        $this->actingAs($employee);
        $this->get(route('approvals.index'))->assertForbidden();

        $this->actingAs($hr);
        $this->post(route('hr.onboarding.store'), [
            'employee_code' => 'APP001',
            'english_first_name' => 'Approval',
            'english_last_name' => 'Tester',
            'english_nickname' => 'App',
            'thai_first_name' => 'Approval',
            'thai_last_name' => 'Tester',
            'thai_nickname' => 'App',
            'department_id' => $department->id,
            'position' => 'IT Support',
            'team' => 'IT Support',
            'location' => 'Lumpini',
            'corporate_email' => 'approval.tester@wdc.co.th',
            'personal_phone' => '0800000000',
            'extension_number' => '1808',
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

        $onboarding = EmployeeOnboardingRequest::where('employee_code', 'APP001')->firstOrFail();

        $this->actingAs($itUser);
        $this->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Approval Center')
            ->assertSee('portal-utility-nav', false)
            ->assertSee('APP001')
            ->assertSee('Approval Tester')
            ->assertSee(route('onboarding.show', $onboarding), false)
            ->assertSee('approval-panel', false)
            ->assertSee('approval-item', false);
    }

    public function test_reports_page_is_visible_to_backoffice_roles_only(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();

        $this->actingAs($employee);
        $this->get(route('reports.index'))->assertForbidden();

        $this->actingAs($hr);
        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('portal-utility-nav', false)
            ->assertSee('รายงานภาพรวม')
            ->assertSee('Ticket ค้าง')
            ->assertSee('Export รายชื่อพนักงาน CSV')
            ->assertDontSee('Export INVENTORY CSV');

        $this->actingAs($itUser);
        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('INVENTORY ตามสถานะ')
            ->assertSee('Export IT Checklist CSV')
            ->assertSee('Export INVENTORY CSV');
    }

    public function test_it_can_register_software_license_and_reports_show_license_summary(): void
    {
        $this->seed(DatabaseSeeder::class);

        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();

        $this->actingAs($itUser);
        $this->post(route('assets.licenses.store'), [
            'code' => 'LIC-M365-001',
            'name' => 'Microsoft 365 Business Standard',
            'vendor' => 'Microsoft',
            'license_type' => 'subscription',
            'seat_count' => 25,
            'assigned_seats' => 18,
            'cost' => 12500,
            'department' => 'IT',
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addDays(45)->toDateString(),
            'status' => 'expiring',
            'notes' => 'Renew before expiry',
        ])->assertRedirect();

        $this->assertDatabaseHas('software_licenses', [
            'code' => 'LIC-M365-001',
            'seat_count' => 25,
            'assigned_seats' => 18,
            'status' => 'expiring',
        ]);

        $license = SoftwareLicense::where('code', 'LIC-M365-001')->firstOrFail();
        $this->assertSame(7, $license->availableSeats());

        $this->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Microsoft 365 Business Standard')
            ->assertSee('LIC-M365-001');

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('License ใกล้หมดอายุ')
            ->assertSee('Software License')
            ->assertSee('ใกล้หมดอายุ');
    }
}
