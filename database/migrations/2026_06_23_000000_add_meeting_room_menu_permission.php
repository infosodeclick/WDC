<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'meeting_rooms.view'],
            [
                'group' => 'Employee Portal',
                'name' => 'ดูห้องประชุม',
                'description' => 'เปิดตารางจองห้องประชุมจาก Google Sheet และปุ่มจองห้องประชุม',
                'sort_order' => 35,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        if (! Schema::hasTable('permission_role') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'meeting_rooms.view')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['employee', 'supervisor', 'hr', 'admin', 'super_admin'])
            ->pluck('id');

        $rows = $roleIds
            ->map(fn (int $roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('permission_role')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', 'meeting_rooms.view')->value('id');

        if ($permissionId && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        if ($permissionId && Schema::hasTable('permission_user')) {
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('key', 'meeting_rooms.view')->delete();
    }
};
