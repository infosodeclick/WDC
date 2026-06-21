<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('permission_user')) {
            return;
        }

        $now = now();
        $assetPermissionIds = DB::table('permissions')
            ->whereIn('key', ['assets.view', 'assets.manage', 'assets.reports'])
            ->pluck('id');

        if ($assetPermissionIds->count() !== 3) {
            return;
        }

        $itPermissionIds = DB::table('permissions')
            ->whereIn('key', ['it.portal.view', 'tickets.manage'])
            ->pluck('id');

        $roleIdsWithItAccess = DB::table('permission_role')
            ->whereIn('permission_id', $itPermissionIds)
            ->pluck('role_id')
            ->unique()
            ->values();

        $userIds = DB::table('users')
            ->whereIn('role_id', $roleIdsWithItAccess)
            ->pluck('id');

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
