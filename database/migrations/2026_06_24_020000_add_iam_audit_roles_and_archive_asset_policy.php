<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();

        foreach (Permission::CATALOG as $permission) {
            if (! in_array($permission['key'], [
                'assets.delete',
                'iam.users.manage',
                'iam.roles.manage',
                'audit.logs.view',
                'audit.logs.export',
                'system.breakglass.use',
            ], true)) {
                continue;
            }

            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
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

        $roles = [
            'iam_admin' => [
                'name' => 'IAM Admin',
                'description' => 'ผู้ดูแลบัญชีและสิทธิ์ตาม approval ไม่ถือสิทธิ์ข้อมูลธุรกิจโดยตรง',
                'default_data_scope' => 'all',
            ],
            'auditor' => [
                'name' => 'Auditor Read-only',
                'description' => 'ผู้ตรวจสอบ อ่าน log รายงาน และหลักฐานโดยไม่แก้ข้อมูล',
                'default_data_scope' => 'all',
            ],
            'it_asset_officer' => [
                'name' => 'IT Asset Officer',
                'description' => 'เจ้าหน้าที่ IT Asset เพิ่ม แก้ไข และติดตามทรัพย์สินตาม scope',
                'default_data_scope' => 'department',
            ],
            'it_asset_admin' => [
                'name' => 'IT Asset Admin',
                'description' => 'ผู้ดูแล master data และการจำหน่าย/เก็บประวัติทรัพย์สิน IT',
                'default_data_scope' => 'all',
            ],
        ];

        foreach ($roles as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    ...$role,
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
            'iam_admin' => [
                'portal.dashboard.view',
                'profile.view',
                'directory.view',
                'iam.users.manage',
                'iam.roles.manage',
                'audit.logs.view',
            ],
            'auditor' => [
                'portal.dashboard.view',
                'profile.view',
                'directory.view',
                'announcements.view',
                'documents.view',
                'assets.view',
                'assets.reports',
                'audit.logs.view',
                'audit.logs.export',
            ],
            'it_asset_officer' => [
                'portal.dashboard.view',
                'profile.view',
                'directory.view',
                'tickets.create',
                'it.portal.view',
                'assets.view',
                'assets.manage',
                'assets.reports',
            ],
            'it_asset_admin' => [
                'portal.dashboard.view',
                'profile.view',
                'directory.view',
                'tickets.create',
                'tickets.manage',
                'it.portal.view',
                'assets.view',
                'assets.manage',
                'assets.reports',
                'assets.settings.manage',
                'assets.delete',
                'audit.logs.view',
            ],
            'super_admin' => Permission::catalogKeys(),
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
    }

    public function down(): void
    {
        //
    }
};
