<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_user') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $now = now();
        $assetKeys = ['assets.view', 'assets.manage', 'assets.reports'];
        $assetPermissionIds = DB::table('permissions')->whereIn('key', $assetKeys)->pluck('id', 'key');

        if ($assetPermissionIds->count() !== count($assetKeys)) {
            return;
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'super_admin'])->pluck('id');

        foreach ($roleIds as $roleId) {
            $rows = $assetPermissionIds->map(fn (int $permissionId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();

            DB::table('permission_role')->insertOrIgnore($rows);
        }

        $itPermissionIds = DB::table('permissions')
            ->whereIn('key', ['it.portal.view', 'tickets.manage'])
            ->pluck('id');

        $userIdsFromItPermissions = DB::table('permission_user')
            ->whereIn('permission_id', $itPermissionIds)
            ->where('effect', 'grant')
            ->pluck('user_id');

        $itDepartmentId = DB::table('departments')->where('code', 'IT')->value('id');
        $userIdsFromItDepartment = $itDepartmentId
            ? DB::table('employees')->where('department_id', $itDepartmentId)->pluck('user_id')
            : collect();

        $userIds = $userIdsFromItPermissions
            ->merge($userIdsFromItDepartment)
            ->unique()
            ->values();

        $rows = $userIds->flatMap(fn (int $userId) => $assetPermissionIds->map(fn (int $permissionId) => [
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'effect' => 'grant',
            'created_at' => $now,
            'updated_at' => $now,
        ]))->values()->all();

        if ($rows !== []) {
            DB::table('permission_user')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        //
    }
};
