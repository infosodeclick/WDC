<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('english_name')->nullable()->after('department_id');
            $table->string('thai_name')->nullable()->after('english_name');
            $table->string('nickname')->nullable()->after('thai_name');
            $table->string('business_unit')->nullable()->after('position');
            $table->string('team')->nullable()->after('business_unit');
            $table->string('location')->nullable()->after('team');
            $table->string('extension_number')->nullable()->after('phone');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('request_type')->default('general')->after('title');
            $table->string('legacy_document_ref')->nullable()->after('image_path');
        });

        Schema::create('legacy_systems', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('url');
            $table->string('login_method');
            $table->text('summary');
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('external_system_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('legacy_system_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('login_identifier')->nullable();
            $table->string('credential_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'legacy_system_id']);
        });

        DB::table('legacy_systems')->insert([
            [
                'key' => 'wdc-portal',
                'name' => 'WDC Portal',
                'category' => 'Portal กลาง',
                'url' => '/dashboard',
                'login_method' => 'รหัสพนักงาน + รหัสผ่านเดียวของ WDC Portal',
                'summary' => 'ระบบใหม่สำหรับ Dashboard, โปรไฟล์พนักงาน, ข่าวสาร, Knowledge Base, Ticket, ร้องเรียน และเอกสาร',
                'status' => 'primary',
                'sort_order' => 1,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'employee-directory',
                'name' => 'WDC Information Directory',
                'category' => 'ข้อมูลพนักงาน',
                'url' => 'https://talented-vulcanodon-8da.notion.site/be7362d68d2c48488559110e03ceea13?v=d8ef55a6b0cc49eabad18939e44be97f',
                'login_method' => 'เปิดอ่านได้ตามลิงก์เดิม ระหว่างย้ายข้อมูลเข้าระบบใหม่',
                'summary' => 'สมุดโทรศัพท์เดิมบน Notion มีชื่อไทย/อังกฤษ ทีม BU ตำแหน่ง ชื่อเล่น สาขา เบอร์ต่อ และกลุ่มอีเมล',
                'status' => 'bridge',
                'sort_order' => 2,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'smartflow-helpdesk',
                'name' => 'SmartFlow IT Helpdesk',
                'category' => 'อนุมัติ / แจ้งซ่อม IT',
                'url' => 'https://wdc.smartflow.pw/document/submit/7/',
                'login_method' => 'อีเมลองค์กร + รหัสผ่าน SmartFlow เดิม',
                'summary' => 'ระบบเดิมสำหรับ IT Helpdesk และ approval workflow เช่น VPN, SAP B1, AI-CRM, Remote Access และยกเลิกเอกสาร',
                'status' => 'bridge',
                'sort_order' => 3,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payroll',
                'name' => 'ระบบสลิปเงินเดือน',
                'category' => 'Payroll',
                'url' => config('services.payroll.url', 'https://example.com/payroll'),
                'login_method' => 'รหัสพนักงาน + เลขบัตรประชาชน ตามระบบเงินเดือนเดิม',
                'summary' => 'ยังไม่เก็บข้อมูลเงินเดือนใน WDC Portal ให้เปิดไปยังระบบเงินเดือนเดิมเพื่อลดความเสี่ยงด้านข้อมูลส่วนบุคคล',
                'status' => 'external',
                'sort_order' => 4,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('employees')
            ->select('employees.id', 'users.name')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->orderBy('employees.id')
            ->each(function (object $employee) {
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update(['thai_name' => $employee->name]);
            });

        $systemIds = DB::table('legacy_systems')->pluck('id', 'key');

        DB::table('users')
            ->select('id', 'employee_code', 'email')
            ->orderBy('id')
            ->each(function (object $user) use ($systemIds) {
                $rows = [
                    'wdc-portal' => [$user->employee_code, 'ใช้รหัสผ่าน WDC Portal'],
                    'employee-directory' => [$user->email, 'เปิดอ่านจากลิงก์เดิม ระหว่างย้าย directory เข้าระบบใหม่'],
                    'smartflow-helpdesk' => [$user->email, 'ใช้รหัสผ่าน SmartFlow เดิม และจะค่อย ๆ ย้ายประเภทคำขอเข้าระบบใหม่'],
                    'payroll' => [$user->employee_code, 'ใช้เลขบัตรประชาชนในระบบเงินเดือนเดิม ไม่เก็บเลขบัตรใน WDC Portal'],
                ];

                foreach ($rows as $systemKey => [$identifier, $note]) {
                    if (! isset($systemIds[$systemKey])) {
                        continue;
                    }

                    DB::table('external_system_accounts')->insertOrIgnore([
                        'user_id' => $user->id,
                        'legacy_system_id' => $systemIds[$systemKey],
                        'login_identifier' => $identifier,
                        'credential_note' => $note,
                        'last_verified_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_system_accounts');
        Schema::dropIfExists('legacy_systems');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['request_type', 'legacy_document_ref']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'english_name',
                'thai_name',
                'nickname',
                'business_unit',
                'team',
                'location',
                'extension_number',
            ]);
        });
    }
};
