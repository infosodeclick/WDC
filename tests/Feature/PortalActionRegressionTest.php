<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\ItAsset;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortalActionRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profile_update_preserves_account_status_and_access_editor_saves_overrides(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('employee_code', 'EMP09999')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.users.access', $employee), [
                'name' => 'สมชาย ทดสอบระบบ',
                'email' => $employee->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $employee->refresh();
        $this->assertTrue($employee->is_active);
        $this->assertSame('สมชาย ทดสอบระบบ', $employee->name);

        $this->actingAs($admin)
            ->patch(route('admin.users.access', $employee), [
                'role_id' => $employee->role_id,
                'data_scope' => 'own',
                'is_active' => 1,
                'permission_denies' => ['announcements.view'],
                'permission_grants' => ['meeting_rooms.view'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $employee = $employee->fresh(['role.permissions', 'permissionOverrides']);
        $this->assertFalse($employee->canAccess('announcements.view'));
        $this->assertTrue($employee->canAccess('meeting_rooms.view'));

        $auditor = Role::where('slug', 'auditor')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.roles.permissions', $auditor), [
                'default_data_scope' => 'all',
                'permissions' => ['profile.view', 'audit.logs.view'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            ['audit.logs.view', 'profile.view'],
            $auditor->fresh()->permissions()->pluck('key')->sort()->values()->all(),
        );
    }

    public function test_reviewing_complaint_stays_in_approval_queue_and_uses_shared_status_label(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $complaint = Complaint::firstOrFail();

        $this->actingAs($hr)
            ->patch(route('complaints.status', $complaint), ['status' => 'reviewing'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'reviewing',
            'assigned_to' => $hr->id,
        ]);

        $this->actingAs($hr)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee($complaint->subject)
            ->assertSee('ตรวจสอบ');

        $this->actingAs($employee)
            ->patch(route('complaints.status', $complaint), ['status' => 'closed'])
            ->assertForbidden();
    }

    public function test_inventory_settings_inspection_and_offline_sync_actions_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $asset = ItAsset::where('code', 'WDC-NB-0001')->firstOrFail();

        $this->actingAs($itUser)
            ->post(route('assets.categories.store'), [
                'code' => 'MOBILE',
                'name' => 'Mobile Device',
                'description' => 'Phone and tablet',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($itUser)
            ->post(route('assets.locations.store'), [
                'code' => 'QA-ROOM',
                'name' => 'QA Room',
                'company' => 'WDC',
                'has_gps' => 1,
                'latitude' => 13.7563,
                'longitude' => 100.5018,
                'radius_meters' => 50,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($itUser)
            ->post(route('assets.inspections.store'), [
                'code' => 'AST-CHK-TEST-0001',
                'inspection_date' => today()->format('Y-m-d'),
                'company' => 'WDC',
                'item_count' => 1,
                'status' => 'open',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $syncFile = UploadedFile::fake()->createWithContent('inventory-sync.json', json_encode([
            'assets' => [[
                'code' => $asset->code,
                'status' => 'repair',
                'department' => 'IT QA',
                'owner_name' => 'QA Team',
                'notes' => 'Updated from mobile inspection',
            ]],
            'documents' => [[
                'code' => 'AST-CHK-SYNC-0001',
                'inspection_date' => today()->format('Y-m-d'),
                'item_count' => 1,
                'status' => 'closed',
            ]],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($itUser)
            ->post(route('assets.sync.import'), ['sync_file' => $syncFile])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('asset_categories', ['code' => 'MOBILE']);
        $this->assertDatabaseHas('asset_locations', ['code' => 'QA-ROOM', 'has_gps' => true]);
        $this->assertDatabaseHas('asset_inspection_documents', ['code' => 'AST-CHK-TEST-0001']);
        $this->assertDatabaseHas('asset_inspection_documents', ['code' => 'AST-CHK-SYNC-0001', 'status' => 'closed']);
        $this->assertDatabaseHas('it_assets', [
            'id' => $asset->id,
            'status' => 'repair',
            'department' => 'IT QA',
            'owner_name' => 'QA Team',
        ]);
        $this->assertDatabaseHas('asset_audit_logs', [
            'it_asset_id' => $asset->id,
            'action' => 'sync_asset',
        ]);
    }

    public function test_hr_employee_status_toggle_keeps_directory_in_sync(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $directoryEntry = EmployeeDirectoryEntry::create([
            'source_system' => 'wdc',
            'user_id' => $employee->id,
            'source_record_id' => $employee->employee_code,
            'entry_type' => 'employee',
            'employment_status' => 'active',
            'display_name' => $employee->name,
            'is_active' => true,
        ]);

        $this->actingAs($hr)
            ->patch(route('hr.employees.status', $employee))
            ->assertRedirect();

        $this->assertFalse($employee->fresh()->is_active);
        $this->assertDatabaseHas('employee_directory_entries', [
            'id' => $directoryEntry->id,
            'is_active' => false,
            'employment_status' => 'resigned',
        ]);

        $this->actingAs($hr)
            ->patch(route('hr.employees.status', $employee))
            ->assertRedirect();

        $this->assertTrue($employee->fresh()->is_active);
        $this->assertDatabaseHas('employee_directory_entries', [
            'id' => $directoryEntry->id,
            'is_active' => true,
            'employment_status' => 'active',
            'resigned_at' => null,
        ]);
    }

    public function test_legacy_ticket_comment_and_status_actions_enforce_roles_and_persist_changes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $ticket = Ticket::where('reporter_id', $employee->id)->firstOrFail();

        $this->actingAs($employee)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'ข้อมูลเพิ่มเติมจากผู้แจ้ง'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
            'body' => 'ข้อมูลเพิ่มเติมจากผู้แจ้ง',
        ]);

        $this->actingAs($employee)
            ->patch(route('tickets.status', $ticket), ['status' => 'done'])
            ->assertForbidden();

        $this->actingAs($itUser)
            ->patch(route('tickets.status', $ticket), ['status' => 'done'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('done', $ticket->status);
        $this->assertSame($itUser->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->completed_at);
    }

    public function test_workflow_favorite_comment_and_template_creation_actions_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $admin = User::where('employee_code', 'EMP09999')->firstOrFail();
        $template = WorkflowTemplate::where('is_active', true)->firstOrFail();
        $workflow = WorkflowRequest::where('requester_id', $employee->id)->firstOrFail();

        $this->actingAs($employee)
            ->post(route('workflows.templates.favorite', $template))
            ->assertRedirect();

        $this->assertTrue(
            $employee->favoriteWorkflowTemplates()->whereKey($template->id)->exists(),
        );

        $this->actingAs($employee)
            ->post(route('workflows.comments.store', $workflow), [
                'comment' => 'ติดตามความคืบหน้าจากหน้า WDC',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_request_events', [
            'workflow_request_id' => $workflow->id,
            'user_id' => $employee->id,
            'action' => 'comment',
            'comment' => 'ติดตามความคืบหน้าจากหน้า WDC',
        ]);

        $this->actingAs($admin)
            ->post(route('workflows.templates.store'), [
                'name' => 'คำขอทดสอบระบบ',
                'category' => 'IT',
                'smartflow_menu' => 'all',
                'approval_policy' => 'manager',
                'description' => 'Workflow สำหรับ regression test',
                'sla_hours' => 24,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_templates', [
            'name' => 'คำขอทดสอบระบบ',
            'source_system' => 'wdc',
            'smartflow_menu' => 'เอกสารทั้งหมด',
            'is_active' => true,
        ]);
    }

    public function test_legacy_admin_user_update_route_preserves_status_and_updates_profile(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('employee_code', 'EMP09999')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $employee), [
                'name' => 'สมชาย เส้นทางเดิม',
                'email' => 'somchai.legacy@wdc.co.th',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $employee->refresh();
        $this->assertTrue($employee->is_active);
        $this->assertSame('สมชาย เส้นทางเดิม', $employee->name);
        $this->assertSame('somchai.legacy@wdc.co.th', $employee->email);
    }

    public function test_it_can_release_claimed_onboarding_and_offboarding_back_to_shared_queue(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hr = User::where('employee_code', 'EMP01000')->firstOrFail();
        $itUser = User::where('employee_code', 'EMP00200')->firstOrFail();
        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();

        $onboarding = EmployeeOnboardingRequest::create([
            'requested_by' => $hr->id,
            'employee_code' => 'EMP88010',
            'english_name' => 'Release Queue',
            'status' => 'in_progress',
            'claimed_by_id' => $itUser->id,
            'claimed_at' => now(),
        ]);

        $offboarding = EmployeeOffboardingRequest::create([
            'requested_by' => $hr->id,
            'employee_user_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'resignation_date' => today(),
            'status' => 'in_progress',
            'claimed_by_id' => $itUser->id,
            'claimed_at' => now(),
        ]);

        $this->actingAs($itUser)
            ->patch(route('it.onboarding.release', $onboarding))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->actingAs($itUser)
            ->patch(route('it.offboarding.release', $offboarding))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('employee_onboarding_requests', [
            'id' => $onboarding->id,
            'status' => 'pending_it',
            'claimed_by_id' => null,
            'claimed_at' => null,
        ]);
        $this->assertDatabaseHas('employee_offboarding_requests', [
            'id' => $offboarding->id,
            'status' => 'pending_it',
            'claimed_by_id' => null,
            'claimed_at' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $itUser->id,
            'action' => 'release_employee_onboarding_it',
            'subject_id' => $onboarding->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $itUser->id,
            'action' => 'release_employee_offboarding_it',
            'subject_id' => $offboarding->id,
        ]);
    }
}
