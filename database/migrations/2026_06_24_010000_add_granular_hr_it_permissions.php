<?php

use App\Models\Permission;
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
        $permissionDefinitions = collect(Permission::CATALOG)
            ->whereIn('key', [
                'directory.manage',
                'assets.settings.manage',
                'assets.delete',
            ])
            ->mapWithKeys(fn (array $permission) => [$permission['key'] => $permission]);

        foreach ($permissionDefinitions as $key => $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => $permission['group'],
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'sort_order' => $permission['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'key');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        $rolePermissions = [
            'hr' => ['directory.manage'],
            'admin' => ['directory.manage', 'assets.settings.manage', 'assets.delete'],
            'super_admin' => ['directory.manage', 'assets.settings.manage', 'assets.delete'],
        ];

        foreach ($rolePermissions as $roleSlug => $permissionKeys) {
            $roleId = $roleIds[$roleSlug] ?? null;

            if (! $roleId) {
                continue;
            }

            $rows = collect($permissionKeys)
                ->map(fn (string $permissionKey) => $permissionIds[$permissionKey] ?? null)
                ->filter()
                ->map(fn (int $permissionId) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all();

            DB::table('permission_role')->insertOrIgnore($rows);
        }

        if (! Schema::hasTable('permission_user')) {
            return;
        }

        $assetPermissionIds = collect(['assets.settings.manage', 'assets.delete'])
            ->map(fn (string $permissionKey) => $permissionIds[$permissionKey] ?? null)
            ->filter();

        if ($assetPermissionIds->isEmpty()) {
            return;
        }

        $legacyAssetManageId = $permissionIds['assets.manage'] ?? null;
        $itPortalId = $permissionIds['it.portal.view'] ?? null;
        $ticketManageId = $permissionIds['tickets.manage'] ?? null;

        $userIds = collect();

        if ($legacyAssetManageId) {
            $userIds = $userIds->merge(
                DB::table('permission_user')
                    ->where('permission_id', $legacyAssetManageId)
                    ->where('effect', 'grant')
                    ->pluck('user_id'),
            );
        }

        $itRoleIds = DB::table('permission_role')
            ->whereIn('permission_id', array_filter([$itPortalId, $ticketManageId, $legacyAssetManageId]))
            ->pluck('role_id');

        if ($itRoleIds->isNotEmpty()) {
            $userIds = $userIds->merge(DB::table('users')->whereIn('role_id', $itRoleIds)->pluck('id'));
        }

        $itDepartmentId = DB::table('departments')->where('code', 'IT')->value('id');

        if ($itDepartmentId) {
            $userIds = $userIds->merge(DB::table('employees')->where('department_id', $itDepartmentId)->pluck('user_id'));
        }

        $rows = $userIds
            ->unique()
            ->flatMap(fn (int $userId) => $assetPermissionIds->map(fn (int $permissionId) => [
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'effect' => 'grant',
                'created_at' => $now,
                'updated_at' => $now,
            ]))
            ->values()
            ->all();

        DB::table('permission_user')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        //
    }
};
