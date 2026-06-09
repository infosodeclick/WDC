<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_directory_entries', function (Blueprint $table) {
            $table->id();
            $table->string('source_system')->default('notion');
            $table->string('entry_type')->default('employee');
            $table->string('display_name');
            $table->string('english_name')->nullable();
            $table->string('thai_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('location')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('extension_number')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('source_system')->default('smartflow');
            $table->string('legacy_workflow_id')->nullable();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('legacy_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name');
            $table->string('mode')->default('any_one');
            $table->string('approver_group')->nullable();
            $table->text('approver_hint')->nullable();
            $table->string('condition_label')->nullable();
            $table->boolean('requires_input')->default(false);
            $table->timestamps();
        });

        Schema::create('workflow_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('requester_id')->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('current_step_id')->nullable()->references('id')->on('workflow_steps')->cascadeOnUpdate()->nullOnDelete();
            $table->string('title');
            $table->longText('details');
            $table->string('priority')->default('normal');
            $table->string('status')->default('submitted');
            $table->string('legacy_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_request_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        $this->seedDirectory();
        $this->seedWorkflows();
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_request_events');
        Schema::dropIfExists('workflow_requests');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('employee_directory_entries');
    }

    private function seedDirectory(): void
    {
        $now = now();
        $rows = [
            ['mail_group', 'accountwdc@wdc.co.th', null, null, null, 'Accounting&Finance', 'Mail Group', 'Lumpini', 'accountwdc@wdc.co.th', null, null, 'กลุ่มอีเมลฝ่ายบัญชีจาก WDC Information Directory'],
            ['employee', 'Aiyada Supso', 'Aiyada Supso', 'อัยย์ญาดา สัพโส', 'กอหญ้า', 'Marketing', 'Senior Digital Marketing', 'Lumpini', null, null, null, null],
            ['employee', 'Alisa Kerdphokha', 'Alisa Kerdphokha', 'อลิสา เกิดโภคา', 'เอ้', 'Bangkok Project (BU2)', 'Sales Executive', 'Nimitmai', null, null, null, 'Bangkok Project Team'],
            ['mail_group', 'all@wdc.co.th', null, null, null, 'All Place', 'Mail Group', 'All Place', 'all@wdc.co.th', null, null, 'ส่งเมลถึงทุกคนใน WDC'],
            ['employee', 'Amolwan Unruean', 'Amolwan Unruean', 'อมลวรรณ อุ่นเรือน', 'มอมแมม', 'Marketing', 'Interior Designer', 'Ratchada', null, null, null, null],
            ['employee', 'Ancharat Lertthanawaree', 'Ancharat Lertthanawaree', 'อัญชรัตน์ เลิศธนวารีย์', 'เบล', 'Bangkok Project (BU2)', 'Sales Executive', 'Nimitmai', null, null, null, 'Bangkok Project Team B'],
            ['employee', 'Anon Ruensook', 'Anon Ruensook', 'อานนท์ รื่นสุข', 'มัส', 'Installation', 'Technician', 'Nimitmai', null, null, null, null],
            ['employee', 'Anyamin Suwapraditporn', 'Anyamin Suwapraditporn', 'อัญมินทร์ สุวประดิษฐ์พร', 'ต๋อม', 'Retail Showroom (BU3)', 'Sales Supervisor', 'CDC', null, null, null, null],
            ['employee', 'Aomsin Bunnit', 'Aomsin Bunnit', 'ออมสิน บุญนิตย์', 'ออม', 'Bangkok Project (BU2)', 'Sales Executive', 'Nimitmai', null, null, null, 'Bangkok Project Team A'],
            ['employee', 'Apinya Ruenkaew', 'Apinya Ruenkaew', 'อภิญญา รื่นแก้ว', 'เตย', 'Accounting&Finance', 'Account Payable Executive', 'Lumpini', null, null, null, null],
            ['employee', 'Apisara Photha', 'Apisara Photha', 'อภิสรา โพธา', 'มายด์', 'Sales Admin', 'Sales Admin Executive', 'Nimitmai', null, null, null, null],
            ['employee', 'Apissara Petchsongkram', 'Apissara Petchsongkram', 'อภิสรา เพ็ชรสงคราม', 'มุก', 'Bangkok Project (BU2)', 'Sales Supervisor', 'Nimitmai', null, null, null, null],
            ['employee', 'Artima Pintanon', 'Artima Pintanon', 'อาทิมา ปิณฑานนท์', 'เมนี่', 'Marketing', 'Interior Designer', 'Lumpini', null, null, null, null],
            ['employee', 'Arwiruth Khehawichitphan', 'Arwiruth Khehawichitphan', 'อวิรุทธ์ เคหะวิจิตรภัณฑ์', 'ป๊อป', 'Modern Trade & Traditional Trade (BU1)', 'Key Account Executive', 'Nimitmai', null, null, null, null],
            ['employee', 'Athichok Sutjapannarot', 'Athichok Sutjapannarot', 'อธิโชค สัจพันโรจน์', 'ไอซ์', 'Accounting&Finance', 'Messenger', 'Nimitmai', null, null, null, null],
            ['employee', 'Atithtaya Chimma', 'Atithtaya Chimma', 'อทิตตยา ฉิมมา', 'มิ้ม', 'Local Project (BU5)', 'Sales Executive', 'Pattaya', null, null, null, null],
            ['employee', 'Boonpitak Meekeaw', 'Boonpitak Meekeaw', 'บุญพิทักษ์ มีแก้ว', 'บู๊ท', 'Warehouse', 'Senior Warehouse Admin', 'Nimitmai', null, null, null, null],
            ['employee', 'Bundit Hirunyanitiwatna', 'Bundit Hirunyanitiwatna', 'บัณฑิต หิรัญญนิธิวัฒนา', 'แบ้งค์', 'Board Management', 'Chief Executive Officer', 'Lumpini', null, null, '8000', null],
            ['employee', 'Buntariga Malawong', 'Buntariga Malawong', 'บุณฑริกา มาละวงษ์', 'ฝน', 'Bangkok Project (BU2)', 'Head of Sales Manager', 'Nimitmai', null, null, null, 'Bangkok Project Team C'],
            ['employee', 'Chadaporn Setthapramort', 'Chadaporn Setthapramort', 'ชฎาพร เศรษฐปราโมทย์', 'หมิว', 'Modern Trade & Traditional Trade (BU1)', 'Interior Designer', 'Lumpini', null, null, null, null],
            ['employee', 'Chalisa Puengphak', 'Chalisa Puengphak', 'ชาลิสา พึ่งพัก', 'ดิว', 'Logistic & Transportation', 'Domestic Logistics Executive', 'Lumpini', null, null, null, null],
            ['employee', 'Chamaikron Hirunrat', 'Chamaikron Hirunrat', 'ชไมกร หิรัญรัตน์', 'แนน', 'Bangkok Project (BU2)', 'Sales Executive', 'Nimitmai', null, null, null, 'Bangkok Project Team C'],
            ['employee', 'Chamaphorn Hirunyanitiwatna', 'Chamaphorn Hirunyanitiwatna', 'ฉามาพร หิรัญญนิธิวัฒนา', 'เอ้', 'Board Management', "CEO's Assistant", 'Lumpini', null, null, '8000', null],
            ['employee', 'Chanapon Jakkaphan', 'Chanapon Jakkaphan', 'ชนะพล จักรพันธ์', 'พล', 'Information Technology', 'IT Support Supervisor', 'Lumpini', 'chanapon.ja@wdc.co.th', null, null, null],
            ['employee', 'Chanintorn Sakonsontised', 'Chanintorn Sakonsontised', 'ชนินทร สกลสนธิเศรษฐ์', 'โอ๋', 'Bangkok Project (BU2)', 'Senior Sales Manager', 'Nimitmai', null, null, null, 'Bangkok Project Team A'],
            ['employee', 'Chatchai Khemvaraporn', 'Chatchai Khemvaraporn', 'ฉัตรชัย เขมวราภรณ์', 'บอส', 'Global Sourcing & Business Development', 'Assistant Inventory Control and Planning Manager', 'Lumpini', null, null, '1401', null],
            ['employee', 'Cherpreme Wimoltrairait', 'Cherpreme Wimoltrairait', 'เฌอพรีม วิมลไตรรัตน์', 'พรีม', 'Secretary', 'Secretary', 'Lumpini', null, null, null, null],
            ['mail_group', 'DPO_privacy@wdc.co.th', null, null, null, 'DPO', 'Mail Group', 'All Place', 'DPO_privacy@wdc.co.th', null, null, 'ช่องทางติดต่อข้อมูลส่วนบุคคลตามประกาศใน Directory'],
            ['mail_group', 'hr_a@wdc.co.th', null, null, null, 'HR', 'Mail Group', 'Lumpini', 'hr_a@wdc.co.th', null, null, null],
            ['mail_group', 'itsupport_g@wdc.co.th', null, null, null, 'IT', 'Mail Group', 'Lumpini', 'itsupport_g@wdc.co.th', null, null, null],
            ['showroom', 'Flagship Showroom รัชดา', null, null, null, 'Showroom', 'Flagship Showroom', 'Ratchada', null, '02-407-9085, 085-496-4946', null, '80, 82 ถนนเทียมร่วมมิตร แขวงห้วยขวาง เขตห้วยขวาง กรุงเทพมหานคร 10310'],
            ['showroom', 'Concept Store ภูเก็ต', null, null, null, 'Showroom', 'Concept Store', 'Phuket', null, '076-304-450, 085-496-4946', null, '78 หมู่ 5 ถนนเฉลิมพระเกียรติ์ ร.9 ตำบลวิชิต อำเภอเมืองภูเก็ต ภูเก็ต 83000'],
        ];

        DB::table('employee_directory_entries')->insert(array_map(fn (array $row) => [
            'entry_type' => $row[0],
            'display_name' => $row[1],
            'english_name' => $row[2],
            'thai_name' => $row[3],
            'nickname' => $row[4],
            'department' => $row[5],
            'position' => $row[6],
            'location' => $row[7],
            'email' => $row[8],
            'phone' => $row[9],
            'extension_number' => $row[10],
            'notes' => $row[11],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }

    private function seedWorkflows(): void
    {
        $now = now();
        $templates = [
            ['1', 'E-MEMO', 'เอกสารภายใน', 'บันทึกข้อความและเอกสารภายในจาก SmartFlow', 'https://wdc.smartflow.pw/document/submit/1/', 10],
            ['2', 'ใบเบิกสินค้า', 'งานคลังสินค้า', 'คำขอเบิกสินค้าพร้อมขั้นตอนอนุมัติ', 'https://wdc.smartflow.pw/document/submit/2/', 20],
            ['3', 'ขอเครดิต/เปิดบัญชีใหม่', 'บัญชี/เครดิต', 'คำขอเปิดบัญชีหรือเครดิตลูกค้าใหม่', 'https://wdc.smartflow.pw/document/submit/3/', 30],
            ['7', 'IT Helpdesk', 'IT Helpdesk', 'แจ้งซ่อม IT, VPN, SAP B1, AI-CRM, Remote Access และยกเลิกเอกสาร', 'https://wdc.smartflow.pw/document/submit/7/', 40],
            ['8', 'ประสานงานภายใน', 'ประสานงาน', 'คำขอประสานงานระหว่างแผนก', 'https://wdc.smartflow.pw/document/submit/8/', 50],
            ['9', 'ขอสำรวจหน้างานและงานติดตั้ง', 'ติดตั้ง', 'คำขอสำรวจหน้างานและติดตั้ง', 'https://wdc.smartflow.pw/document/submit/9/', 60],
            ['10', 'ขออนุมัติราคา/ขายสินค้า', 'ฝ่ายขาย', 'คำขออนุมัติราคาและการขายสินค้า', 'https://wdc.smartflow.pw/document/submit/10/', 70],
            ['13', 'Developer/IT support', 'IT Helpdesk', 'งานสนับสนุน Developer และ IT', 'https://wdc.smartflow.pw/document/submit/13/', 80],
            ['14', 'ขออนุมัติคอนเทนต์ (Marketing)', 'Marketing', 'คำขออนุมัติคอนเทนต์ฝ่าย Marketing', 'https://wdc.smartflow.pw/document/submit/14/', 90],
        ];

        foreach ($templates as $template) {
            $id = DB::table('workflow_templates')->insertGetId([
                'legacy_workflow_id' => $template[0],
                'name' => $template[1],
                'category' => $template[2],
                'description' => $template[3],
                'legacy_url' => $template[4],
                'sort_order' => $template[5],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $steps = $template[0] === '7'
                ? [
                    [1, 'Manager Approval', 'Senior_Management หรือผู้จัดการที่เลือก', 'ใช้เมื่อเป็นการยกเลิกเอกสาร', false],
                    [2, 'Accept Case', 'ทีม IT', 'รับเรื่องโดย IT', false],
                    [3, 'Resolve Case', 'ทีม IT', 'ต้องใส่ผลการแก้ไข', true],
                    [4, 'AI-CRM Accept Case', 'AI System', 'ใช้เมื่อเลือกแจ้งปัญหา AI-CRM', false],
                    [5, 'AI-CRM Resolve Case', 'AI System', 'ใช้เมื่อเลือกแจ้งปัญหา AI-CRM', true],
                    [6, 'SoftpowerIT Accept Case', 'SoftpowerIT', 'ใช้เมื่อเลือกแจ้งปัญหา SAP B1', false],
                    [7, 'SoftpowerIT Resolve Case', 'SoftpowerIT', 'ใช้เมื่อเลือกแจ้งปัญหา SAP B1', true],
                ]
                : [
                    [1, 'Submit Request', 'ผู้ร้องขอ', 'ส่งคำขอเข้าระบบ', false],
                    [2, 'Manager Review', 'ผู้จัดการหรือผู้อนุมัติ', 'ตรวจสอบรายละเอียด', false],
                    [3, 'Final Approval', 'ผู้มีอำนาจอนุมัติ', 'อนุมัติหรือปฏิเสธคำขอ', true],
                ];

            foreach ($steps as $step) {
                DB::table('workflow_steps')->insert([
                    'workflow_template_id' => $id,
                    'step_order' => $step[0],
                    'name' => $step[1],
                    'approver_group' => $step[2],
                    'approver_hint' => $step[2],
                    'condition_label' => $step[3],
                    'requires_input' => $step[4],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
