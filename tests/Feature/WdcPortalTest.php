<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Permission;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertSee('สวัสดี คุณสมชาย')
            ->assertSee('ประกาศใหม่')
            ->assertSee('Ticket ค้าง');
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
            ->assertSee('Super Admin Console')
            ->assertSee('Role Template')
            ->assertSee('EMP00125');
    }

    public function test_employee_can_open_systems_hub(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post(route('login.store'), [
            'employee_code' => 'EMP00125',
            'password' => 'password123',
        ]);

        $this->get(route('systems.index'))
            ->assertOk()
            ->assertSee('ศูนย์รวมระบบ WDC')
            ->assertSee('WDC Information Directory')
            ->assertSee('SmartFlow IT Helpdesk')
            ->assertSee('ระบบสลิปเงินเดือน')
            ->assertDontSee('Qa741852');
    }

    public function test_user_permission_override_can_block_frontend_route(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('employee_code', 'EMP00125')->firstOrFail();
        $permission = Permission::where('key', 'systems.view')->firstOrFail();

        $employee->permissionOverrides()->sync([
            $permission->id => ['effect' => 'deny'],
        ]);

        $this->actingAs($employee);

        $this->get(route('systems.index'))->assertForbidden();
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
            ->assertSee('รายชื่อพนักงาน')
            ->assertSee('Chanapon Jakkaphan')
            ->assertSee('Information Technology');
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
        $workflowRequest = WorkflowRequest::create([
            'workflow_template_id' => $template->id,
            'requester_id' => 1,
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
        ])->assertRedirect(route('tickets.index'));

        $ticket = Ticket::where('title', 'ขอใช้งาน VPN')->firstOrFail();

        $this->assertSame('vpn_access', $ticket->request_type);
        $this->assertSame('REF: #2606815', $ticket->legacy_document_ref);
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
}
