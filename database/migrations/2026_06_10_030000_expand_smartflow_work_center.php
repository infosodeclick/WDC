<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->string('smartflow_menu')->default('Workflows')->after('description');
            $table->string('service_team')->nullable()->after('smartflow_menu');
            $table->json('form_schema')->nullable()->after('service_team');
            $table->unsignedSmallInteger('sla_hours')->nullable()->after('form_schema');
            $table->string('approval_policy')->default('any_one')->after('sla_hours');
            $table->index(['is_active', 'sort_order'], 'workflow_templates_active_sort_idx');
            $table->index(['source_system', 'legacy_workflow_id'], 'workflow_templates_source_legacy_idx');
        });

        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('current_step_id')->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('document_number')->nullable()->unique()->after('assigned_to');
            $table->string('smartflow_menu')->default('All Documents')->after('document_number');
            $table->json('form_payload')->nullable()->after('details');
            $table->string('assigned_group')->nullable()->after('form_payload');
            $table->timestamp('due_at')->nullable()->after('legacy_reference');
            $table->index(['requester_id', 'status'], 'workflow_requests_requester_status_idx');
            $table->index(['status', 'due_at'], 'workflow_requests_status_due_idx');
            $table->index(['workflow_template_id', 'status'], 'workflow_requests_template_status_idx');
            $table->index(['assigned_to', 'status'], 'workflow_requests_assigned_status_idx');
        });

        Schema::create('workflow_template_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'workflow_template_id'], 'workflow_template_favorites_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['reporter_id', 'status'], 'tickets_reporter_status_idx');
            $table->index(['department_id', 'status'], 'tickets_department_status_idx');
            $table->index(['request_type', 'status'], 'tickets_request_type_status_idx');
            $table->index(['status', 'completed_at'], 'tickets_status_completed_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_user_read_idx');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['published_at', 'expires_at'], 'announcements_publish_expire_idx');
            $table->index(['is_pinned', 'published_at'], 'announcements_pinned_publish_idx');
        });

        Schema::table('knowledge_videos', function (Blueprint $table) {
            $table->index(['is_published', 'published_at'], 'knowledge_videos_published_idx');
        });

        Schema::table('employee_directory_entries', function (Blueprint $table) {
            $table->index(['is_active', 'entry_type'], 'directory_active_type_idx');
            $table->index(['is_active', 'department'], 'directory_active_department_idx');
            $table->index(['is_active', 'team'], 'directory_active_team_idx');
            $table->index(['is_active', 'location'], 'directory_active_location_idx');
        });

        $this->syncSmartflowTemplates();
        $this->backfillDocumentNumbers();
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_template_favorites');

        Schema::table('employee_directory_entries', function (Blueprint $table) {
            $table->dropIndex('directory_active_type_idx');
            $table->dropIndex('directory_active_department_idx');
            $table->dropIndex('directory_active_team_idx');
            $table->dropIndex('directory_active_location_idx');
        });

        Schema::table('knowledge_videos', function (Blueprint $table) {
            $table->dropIndex('knowledge_videos_published_idx');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_publish_expire_idx');
            $table->dropIndex('announcements_pinned_publish_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_idx');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_reporter_status_idx');
            $table->dropIndex('tickets_department_status_idx');
            $table->dropIndex('tickets_request_type_status_idx');
            $table->dropIndex('tickets_status_completed_idx');
        });

        Schema::table('workflow_requests', function (Blueprint $table) {
            $table->dropIndex('workflow_requests_requester_status_idx');
            $table->dropIndex('workflow_requests_status_due_idx');
            $table->dropIndex('workflow_requests_template_status_idx');
            $table->dropIndex('workflow_requests_assigned_status_idx');
            $table->dropUnique(['document_number']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn([
                'document_number',
                'smartflow_menu',
                'form_payload',
                'assigned_group',
                'due_at',
            ]);
        });

        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->dropIndex('workflow_templates_active_sort_idx');
            $table->dropIndex('workflow_templates_source_legacy_idx');
            $table->dropColumn([
                'smartflow_menu',
                'service_team',
                'form_schema',
                'sla_hours',
                'approval_policy',
            ]);
        });
    }

    private function syncSmartflowTemplates(): void
    {
        $now = now();
        $templates = [
            '1' => ['E-MEMO', 'เอกสารภายใน', 'Authorization', 'ผู้จัดการต้นสังกัด', 48, 'บันทึกข้อความภายใน ส่งต่อผู้อนุมัติตามสายงาน', ['เรื่อง', 'ถึง', 'สำเนา', 'เหตุผล/รายละเอียด', 'ไฟล์แนบ']],
            '2' => ['ใบเบิกสินค้า', 'คลังสินค้า', 'Authorization', 'Warehouse / Supervisor', 48, 'เบิกสินค้าเข้ากระบวนการคลังพร้อมตรวจสอบจำนวนและผู้อนุมัติ', ['สินค้า', 'จำนวน', 'คลัง/สาขา', 'วันที่ต้องการ', 'เหตุผล']],
            '3' => ['ขอเครดิต/เปิดบัญชีใหม่', 'บัญชี/เครดิต', 'Authorization', 'Accounting & Finance', 72, 'ขอเปิดบัญชีลูกค้าใหม่หรือขอวงเงินเครดิต', ['ชื่อลูกค้า', 'เลขผู้เสียภาษี', 'วงเงิน', 'เงื่อนไขเครดิต', 'เอกสารประกอบ']],
            '7' => ['IT Helpdesk', 'IT Helpdesk', 'Your Tasks', 'IT Helpdesk', 24, 'แจ้งงาน IT แบบ SmartFlow: manager approval, accept case, resolve case และแยก AI-CRM/SAP B1', ['ประเภทปัญหา', 'ระบบที่เกี่ยวข้อง', 'อุปกรณ์/ผู้ใช้', 'ความเร่งด่วน', 'รูปหรือหลักฐาน']],
            '8' => ['ประสานงานภายใน', 'ประสานงาน', 'All Documents', 'หน่วยงานปลายทาง', 48, 'ส่งงานประสานงานระหว่างแผนก ติดตามสถานะ และเก็บประวัติกลาง', ['แผนกปลายทาง', 'ผู้เกี่ยวข้อง', 'วันที่ต้องการ', 'รายละเอียดงาน', 'ผลลัพธ์ที่ต้องการ']],
            '9' => ['ขอสำรวจหน้างานและงานติดตั้ง', 'ติดตั้ง', 'Authorization', 'Installation', 72, 'ส่งงานสำรวจหน้างานและติดตั้งพร้อมข้อมูลลูกค้า สถานที่ และเวลานัดหมาย', ['ลูกค้า/โครงการ', 'สถานที่', 'วันนัดหมาย', 'ผู้ติดต่อ', 'รายละเอียดพื้นที่']],
            '10' => ['ขออนุมัติราคา/ขายสินค้า', 'ฝ่ายขาย', 'Authorization', 'Sales Management', 48, 'ขออนุมัติราคา ส่วนลด หรือเงื่อนไขการขาย', ['ลูกค้า', 'สินค้า/รุ่น', 'ราคาขาย', 'ส่วนลด', 'เหตุผลขออนุมัติ']],
            '13' => ['Developer/IT support', 'IT Helpdesk', 'Your Tasks', 'Developer / IT', 48, 'งานสนับสนุนระบบ โปรแกรม รายงาน หรือ integration', ['ระบบ', 'ประเภทงาน', 'ผลกระทบ', 'ตัวอย่างข้อมูล', 'กำหนดส่ง']],
            '14' => ['ขออนุมัติคอนเทนต์ (Marketing)', 'Marketing', 'Authorization', 'Marketing / Management', 48, 'ขออนุมัติคอนเทนต์ก่อนเผยแพร่ เก็บไฟล์และความเห็นไว้ใน WDC', ['ช่องทางเผยแพร่', 'แคมเปญ', 'วันเผยแพร่', 'ผู้ตรวจ', 'ลิงก์ไฟล์งาน']],
        ];

        foreach ($templates as $legacyId => [$name, $category, $menu, $team, $slaHours, $description, $fields]) {
            DB::table('workflow_templates')->updateOrInsert(
                ['source_system' => 'smartflow', 'legacy_workflow_id' => $legacyId],
                [
                    'name' => $name,
                    'category' => $category,
                    'smartflow_menu' => $menu,
                    'service_team' => $team,
                    'description' => $description,
                    'form_schema' => json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE),
                    'sla_hours' => $slaHours,
                    'approval_policy' => 'any_one',
                    'legacy_url' => "https://wdc.smartflow.pw/document/submit/{$legacyId}/",
                    'is_active' => true,
                    'sort_order' => ((int) $legacyId) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function backfillDocumentNumbers(): void
    {
        DB::table('workflow_requests')
            ->whereNull('document_number')
            ->orderBy('id')
            ->select('id', 'created_at')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    $createdAt = $request->created_at ? \Illuminate\Support\Carbon::parse($request->created_at) : now();

                    DB::table('workflow_requests')
                        ->where('id', $request->id)
                        ->update([
                            'document_number' => 'WDC-SF-'.$createdAt->format('Ymd').'-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT),
                            'smartflow_menu' => 'All Documents',
                            'updated_at' => now(),
                        ]);
                }
            });
    }
};
