<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('key', 'directory.view')->update([
                'name' => 'ดูรายชื่อพนักงาน',
                'description' => 'ค้นหาพนักงาน ทีม สาขา อีเมล และเบอร์ต่อ',
            ]);
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if (! $superAdminRoleId) {
            $superAdminRoleId = DB::table('roles')->insertGetId([
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'ผู้ดูแลสูงสุด แก้สิทธิ์และระบบหลังบ้านทั้งหมด',
                'default_data_scope' => 'all',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $userPayload = [
            'role_id' => $superAdminRoleId,
            'name' => 'Administrator',
            'email' => null,
            'password' => '$2y$12$q0clmzOHt/Lg1ZsXXkQfSeNOEgYIcX1.YdJu4xOWjkjAldk6F6XPi',
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('users', 'data_scope')) {
            $userPayload['data_scope'] = 'all';
        }

        DB::table('users')->updateOrInsert(
            ['employee_code' => 'administrator'],
            [
                ...$userPayload,
                'employee_code' => 'administrator',
                'created_at' => $now,
            ],
        );

        $administratorId = DB::table('users')->where('employee_code', 'administrator')->value('id');

        if ($administratorId && Schema::hasTable('departments') && Schema::hasTable('employees')) {
            $departmentId = DB::table('departments')->where('code', 'ADMIN')->value('id');

            if (! $departmentId) {
                $departmentId = DB::table('departments')->insertGetId([
                    'code' => 'ADMIN',
                    'name' => 'ผู้ดูแลระบบ',
                    'description' => 'ทีมดูแลระบบ WDC Portal',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('employees')->updateOrInsert(
                ['user_id' => $administratorId],
                [
                    'user_id' => $administratorId,
                    'department_id' => $departmentId,
                    'position' => 'System Administrator',
                    'phone' => null,
                    'start_date' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        if (Schema::hasTable('permission_role') && Schema::hasTable('permissions')) {
            $permissionRows = DB::table('permissions')
                ->pluck('id')
                ->map(fn (int $permissionId) => [
                    'role_id' => $superAdminRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($permissionRows !== []) {
                DB::table('permission_role')->insertOrIgnore($permissionRows);
            }
        }

        if (Schema::hasTable('legacy_systems')) {
            DB::table('legacy_systems')
                ->where('key', 'employee-directory')
                ->update([
                    'name' => 'รายชื่อพนักงาน',
                    'summary' => 'รายชื่อพนักงานเดิมจาก Notion มีชื่อไทย/อังกฤษ ทีม BU ตำแหน่ง ชื่อเล่น สาขา เบอร์ต่อ และกลุ่มอีเมล',
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where('employee_code', 'administrator')->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->where('key', 'directory.view')->update([
                'name' => 'ดูสมุดโทรศัพท์',
                'description' => 'ค้นหาพนักงาน ทีม สาขา อีเมล และเบอร์ต่อ',
            ]);
        }
    }
};
