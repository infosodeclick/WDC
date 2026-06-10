<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_directory_entries', function (Blueprint $table) {
            $table->string('source_record_id')->nullable()->after('source_system');
            $table->string('source_url')->nullable()->after('source_record_id');
            $table->string('image_url', 1200)->nullable()->after('source_url');
            $table->string('team')->nullable()->after('department');
            $table->json('raw_payload')->nullable()->after('notes');
            $table->timestamp('imported_at')->nullable()->after('raw_payload');

            $table->unique(['source_system', 'source_record_id'], 'directory_source_record_unique');
        });

        Schema::create('legacy_system_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('source_system');
            $table->string('snapshot_type');
            $table->string('title');
            $table->string('source_url', 1000)->nullable();
            $table->text('summary');
            $table->json('payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('legacy_system_snapshots')->insert([
            [
                'source_system' => 'notion',
                'snapshot_type' => 'directory_model',
                'title' => 'WDC Information Directory',
                'source_url' => 'https://talented-vulcanodon-8da.notion.site/be7362d68d2c48488559110e03ceea13?v=d8ef55a6b0cc49eabad18939e44be97f',
                'summary' => 'สมุดโทรศัพท์เดิมบน Notion ใช้เป็นฐานข้อมูลติดต่อภายใน มีมุมมอง By location, All team members, By team และ Table view พร้อมประกาศ PDPA ว่าใช้เพื่อสื่อสาร/ประสานงานภายในองค์กรเท่านั้น',
                'payload' => json_encode([
                    'observed_total' => 192,
                    'observed_breakdown' => [
                        'employees' => 150,
                        'mail_groups' => 19,
                        'showrooms' => 23,
                    ],
                    'fields' => [
                        'Name',
                        'ชื่อภาษาไทย',
                        'Department',
                        'Team',
                        'Job Title',
                        'Nickname',
                        'Location',
                        'Email',
                        'Ext.',
                    ],
                    'views' => [
                        'By location',
                        'All team members',
                        'By team',
                        'Table view',
                    ],
                    'pdpa_rule' => 'ใช้เพื่อการติดต่อและประสานงานภายในองค์กร ห้ามเผยแพร่หรือใช้เพื่อวัตถุประสงค์อื่นโดยไม่ได้รับอนุญาต',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'source_system' => 'smartflow',
                'snapshot_type' => 'workflow_model',
                'title' => 'SmartFlow workflow and IT Helpdesk model',
                'source_url' => 'https://wdc.smartflow.pw/',
                'summary' => 'SmartFlow เดิมเป็นระบบเอกสาร/อนุมัติ มีเมนู All Documents, Your Tasks, Authorization, Statistics, Export Excel, Favorites และ Workflows โดย IT Helpdesk ใช้ flow รับเรื่องและปิดงานแยกตามประเภทคำขอ เช่น AI-CRM และ SAP B1',
                'payload' => json_encode([
                    'main_menus' => [
                        'All Documents' => '/document/',
                        'Your Tasks' => '/document/to-approve/',
                        'Authorization' => '/document/authorizations/',
                        'Statistics' => '/document/statistics/',
                        'Export Excel' => '/document/export/',
                        'Favorites' => '/document/favorite-documents/',
                        'Workflows' => '/document/workflows/',
                    ],
                    'workflow_templates' => [
                        ['id' => 1, 'name' => 'E-MEMO'],
                        ['id' => 2, 'name' => 'ใบเบิกสินค้า'],
                        ['id' => 3, 'name' => 'ขอเครดิต/เปิดบัญชีใหม่'],
                        ['id' => 7, 'name' => 'IT Helpdesk'],
                        ['id' => 8, 'name' => 'ประสานงานภายใน'],
                        ['id' => 9, 'name' => 'ขอสำรวจหน้างานและงานติดตั้ง'],
                        ['id' => 10, 'name' => 'ขออนุมัติราคา/ขายสินค้า'],
                        ['id' => 13, 'name' => 'Developer/IT support'],
                        ['id' => 14, 'name' => 'ขออนุมัติคอนเทนต์ (Marketing)'],
                    ],
                    'it_helpdesk_steps' => [
                        [
                            'name' => 'Manager Approval',
                            'mode' => 'Any One',
                            'approvers' => 'Senior_Management หรือผู้จัดการที่เลือก',
                            'condition' => 'ใช้เมื่อเลือก Cancel Document',
                            'requires_input' => false,
                        ],
                        [
                            'name' => 'Accept Case',
                            'mode' => 'Any One',
                            'approvers' => 'พีรสิทธิ์ หนองรั้ง, ชนะพล จักรพันธ์',
                            'condition' => 'ทีม IT รับเรื่อง',
                            'requires_input' => false,
                        ],
                        [
                            'name' => 'Resolve Case',
                            'mode' => 'Any One',
                            'approvers' => 'พีรสิทธิ์ หนองรั้ง, ชนะพล จักรพันธ์',
                            'condition' => 'ทีม IT ใส่ผลการแก้ไขเพื่อปิดงาน',
                            'requires_input' => true,
                        ],
                        [
                            'name' => 'AI-CRM Accept Case',
                            'mode' => 'Any One',
                            'approvers' => 'thipaporn aisystem',
                            'condition' => 'ใช้เมื่อเลือก AI-CRM',
                            'requires_input' => false,
                        ],
                        [
                            'name' => 'AI-CRM Resolve Case',
                            'mode' => 'Any One',
                            'approvers' => 'thipaporn aisystem',
                            'condition' => 'ใช้เมื่อเลือก AI-CRM',
                            'requires_input' => true,
                        ],
                        [
                            'name' => 'SoftpowerIT Accept Case',
                            'mode' => 'Any One',
                            'approvers' => 'ทีม SoftpowerIT สำหรับ SAP B1',
                            'condition' => 'ใช้เมื่อเลือก SAP B1',
                            'requires_input' => false,
                        ],
                        [
                            'name' => 'SoftpowerIT Resolve Case',
                            'mode' => 'Any One',
                            'approvers' => 'ทีม SoftpowerIT สำหรับ SAP B1',
                            'condition' => 'ใช้เมื่อเลือก SAP B1',
                            'requires_input' => true,
                        ],
                    ],
                    'replacement_direction' => [
                        'ให้พนักงานเริ่มที่ WDC Portal ด้วยบัญชีเดียว',
                        'คงลิงก์ SmartFlow เดิมเฉพาะงานที่ยังต้องใช้เอกสารเก่า',
                        'ย้ายประเภทคำขอที่ใช้บ่อย เช่น IT Helpdesk และ Developer support เข้า WDC ก่อน',
                        'ไม่เก็บรหัสผ่าน SmartFlow ใน WDC Portal',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $stepUpdates = [
            'Manager Approval' => [
                'mode' => 'any_one',
                'approver_group' => 'Senior_Management',
                'approver_hint' => 'Senior_Management หรือผู้จัดการที่ผู้แจ้งเลือก',
                'condition_label' => 'ใช้เมื่อเป็นคำขอยกเลิกเอกสาร',
            ],
            'Accept Case' => [
                'mode' => 'any_one',
                'approver_group' => 'IT Helpdesk',
                'approver_hint' => 'พีรสิทธิ์ หนองรั้ง, ชนะพล จักรพันธ์',
                'condition_label' => 'ทีม IT รับเรื่อง',
            ],
            'Resolve Case' => [
                'mode' => 'any_one',
                'approver_group' => 'IT Helpdesk',
                'approver_hint' => 'พีรสิทธิ์ หนองรั้ง, ชนะพล จักรพันธ์',
                'condition_label' => 'ทีม IT ใส่ผลการแก้ไข',
            ],
            'AI-CRM Accept Case' => [
                'mode' => 'any_one',
                'approver_group' => 'AI System',
                'approver_hint' => 'thipaporn aisystem',
                'condition_label' => 'ใช้เมื่อเลือกประเภท AI-CRM',
            ],
            'AI-CRM Resolve Case' => [
                'mode' => 'any_one',
                'approver_group' => 'AI System',
                'approver_hint' => 'thipaporn aisystem',
                'condition_label' => 'ใช้เมื่อเลือกประเภท AI-CRM',
            ],
            'SoftpowerIT Accept Case' => [
                'mode' => 'any_one',
                'approver_group' => 'SoftpowerIT',
                'approver_hint' => 'ทีม SoftpowerIT สำหรับ SAP B1',
                'condition_label' => 'ใช้เมื่อเลือกประเภท SAP B1',
            ],
            'SoftpowerIT Resolve Case' => [
                'mode' => 'any_one',
                'approver_group' => 'SoftpowerIT',
                'approver_hint' => 'ทีม SoftpowerIT สำหรับ SAP B1',
                'condition_label' => 'ใช้เมื่อเลือกประเภท SAP B1',
            ],
        ];

        foreach ($stepUpdates as $name => $update) {
            DB::table('workflow_steps')->where('name', $name)->update([
                ...$update,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_system_snapshots');

        Schema::table('employee_directory_entries', function (Blueprint $table) {
            $table->dropUnique('directory_source_record_unique');
            $table->dropColumn([
                'source_record_id',
                'source_url',
                'image_url',
                'team',
                'raw_payload',
                'imported_at',
            ]);
        });
    }
};
