<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Ticket;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('จัดการผู้ใช้งานและ Log')
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
            'type' => 'เสนอแนะ',
            'submitted_to' => 'hr',
            'subject' => 'ทดสอบไม่เปิดเผยชื่อ',
            'details' => 'ต้องไม่เก็บ reporter_id เมื่อเลือกไม่เปิดเผยชื่อ',
            'is_anonymous' => '1',
        ])->assertRedirect(route('complaints.index'));

        $complaint = Complaint::where('subject', 'ทดสอบไม่เปิดเผยชื่อ')->firstOrFail();

        $this->assertTrue($complaint->is_anonymous);
        $this->assertNull($complaint->reporter_id);
    }
}
