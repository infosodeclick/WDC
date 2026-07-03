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
        $roles = [
            'employee' => [
                'name' => 'Employee',
                'description' => 'Standard employee portal access',
                'scope' => 'own',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'meeting_rooms.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'complaints.create',
                ],
            ],
            'hr' => [
                'name' => 'HR',
                'description' => 'HR portal, employee records, announcements, documents, and complaints',
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'directory.manage',
                    'meeting_rooms.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.onboarding.manage',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                ],
            ],
            'it_supervisor' => [
                'name' => 'IT Supervisor',
                'description' => 'IT helpdesk, onboarding, inventory administration, and reports',
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'directory.manage',
                    'meeting_rooms.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'payroll.link',
                    'tickets.create',
                    'tickets.manage',
                    'it.portal.view',
                    'it.onboarding.manage',
                    'assets.view',
                    'assets.manage',
                    'assets.reports',
                    'assets.settings.manage',
                    'assets.delete',
                    'workflows.create',
                    'workflows.manage',
                ],
            ],
            'it_support' => [
                'name' => 'IT Support',
                'description' => 'IT helpdesk, onboarding, inventory operation, and reports',
                'scope' => 'department',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'directory.manage',
                    'meeting_rooms.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'payroll.link',
                    'tickets.create',
                    'tickets.manage',
                    'it.portal.view',
                    'it.onboarding.manage',
                    'assets.view',
                    'assets.manage',
                    'assets.reports',
                    'workflows.create',
                    'workflows.manage',
                ],
            ],
            'admin' => [
                'name' => 'Admin',
                'description' => 'System administration, users, roles, settings, and logs',
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'directory.manage',
                    'meeting_rooms.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'payroll.link',
                    'tickets.create',
                    'tickets.manage',
                    'it.portal.view',
                    'it.onboarding.manage',
                    'assets.view',
                    'assets.manage',
                    'assets.reports',
                    'assets.settings.manage',
                    'assets.delete',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.onboarding.manage',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                    'admin.users.manage',
                    'admin.roles.manage',
                    'admin.activity.view',
                    'admin.system.manage',
                    'iam.users.manage',
                    'iam.roles.manage',
                    'audit.logs.view',
                    'audit.logs.export',
                ],
            ],
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Highest-level administrator with all permissions',
                'scope' => 'all',
                'permissions' => Permission::catalogKeys(),
            ],
            'auditor' => [
                'name' => 'Auditor Read-only',
                'description' => 'Read-only audit, reports, and evidence access',
                'scope' => 'all',
                'permissions' => [
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
            ],
        ];

        foreach ($roles as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'default_data_scope' => $role['scope'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $legacyRoleMap = [
            'supervisor' => 'it_supervisor',
            'it_asset_admin' => 'it_supervisor',
            'it_asset_officer' => 'it_support',
            'iam_admin' => 'admin',
        ];

        if (Schema::hasTable('users')) {
            foreach ($legacyRoleMap as $oldSlug => $newSlug) {
                if (! isset($roleIds[$oldSlug], $roleIds[$newSlug])) {
                    continue;
                }

                DB::table('users')
                    ->where('role_id', $roleIds[$oldSlug])
                    ->update([
                        'role_id' => $roleIds[$newSlug],
                        'updated_at' => $now,
                    ]);
            }
        }

        if (Schema::hasTable('permission_role')) {
            $permissionIds = DB::table('permissions')->pluck('id', 'key');
            $standardRoleIds = collect($roles)
                ->keys()
                ->map(fn (string $slug) => $roleIds[$slug] ?? null)
                ->filter()
                ->values();

            DB::table('permission_role')->whereIn('role_id', $standardRoleIds)->delete();

            foreach ($roles as $slug => $role) {
                $roleId = $roleIds[$slug] ?? null;

                if (! $roleId) {
                    continue;
                }

                $rows = collect($role['permissions'])
                    ->map(fn (string $permissionKey) => $permissionIds[$permissionKey] ?? null)
                    ->filter()
                    ->unique()
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

            $legacyRoleIds = collect(array_keys($legacyRoleMap))
                ->map(fn (string $slug) => $roleIds[$slug] ?? null)
                ->filter()
                ->values();

            DB::table('permission_role')->whereIn('role_id', $legacyRoleIds)->delete();
        }

        $keepSlugs = array_keys($roles);

        if (Schema::hasTable('users') && isset($roleIds['employee'])) {
            $obsoleteRoleIds = DB::table('roles')->whereNotIn('slug', $keepSlugs)->pluck('id');

            if ($obsoleteRoleIds->isNotEmpty()) {
                DB::table('users')
                    ->whereIn('role_id', $obsoleteRoleIds)
                    ->update([
                        'role_id' => $roleIds['employee'],
                        'updated_at' => $now,
                    ]);
            }
        }

        if (Schema::hasTable('permission_role')) {
            $obsoleteRoleIds = DB::table('roles')->whereNotIn('slug', $keepSlugs)->pluck('id');
            DB::table('permission_role')->whereIn('role_id', $obsoleteRoleIds)->delete();
        }

        DB::table('roles')->whereNotIn('slug', $keepSlugs)->delete();
    }

    public function down(): void
    {
        //
    }
};
