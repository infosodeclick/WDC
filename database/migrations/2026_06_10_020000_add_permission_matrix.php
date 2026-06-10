<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('default_data_scope')->default('own')->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('data_scope')->nullable()->after('role_id');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('effect', 12);
            $table->timestamps();
            $table->unique(['permission_id', 'user_id']);
        });

        $now = now();
        $permissions = collect(Permission::CATALOG)
            ->map(fn (array $permission) => [
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('permissions')->insert($permissions);

        DB::table('roles')->updateOrInsert(
            ['slug' => 'super_admin'],
            [
                'name' => 'Super Admin',
                'description' => 'ผู้ดูแลสูงสุด แก้สิทธิ์และระบบหลังบ้านทั้งหมด',
                'default_data_scope' => 'all',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $roleDefaults = [
            'employee' => [
                'scope' => 'own',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'complaints.create',
                ],
            ],
            'supervisor' => [
                'scope' => 'department',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                ],
            ],
            'hr' => [
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                ],
            ],
            'admin' => [
                'scope' => 'all',
                'permissions' => [
                    'portal.dashboard.view',
                    'profile.view',
                    'directory.view',
                    'announcements.view',
                    'knowledge.view',
                    'documents.view',
                    'documents.manage',
                    'systems.view',
                    'payroll.link',
                    'tickets.create',
                    'tickets.manage',
                    'it.portal.view',
                    'workflows.create',
                    'workflows.manage',
                    'complaints.create',
                    'complaints.review',
                    'hr.portal.view',
                    'hr.employees.manage',
                    'hr.announcements.manage',
                    'admin.users.manage',
                    'admin.activity.view',
                ],
            ],
            'super_admin' => [
                'scope' => 'all',
                'permissions' => Permission::catalogKeys(),
            ],
        ];

        $permissionIds = DB::table('permissions')->pluck('id', 'key');
        $roleIds = DB::table('roles')->pluck('id', 'slug');

        foreach ($roleDefaults as $roleSlug => $definition) {
            $roleId = $roleIds[$roleSlug] ?? null;

            if (! $roleId) {
                continue;
            }

            DB::table('roles')
                ->where('id', $roleId)
                ->update([
                    'default_data_scope' => $definition['scope'],
                    'updated_at' => $now,
                ]);

            $rows = collect($definition['permissions'])
                ->map(fn (string $permissionKey) => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds[$permissionKey],
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('permission_role')->insertOrIgnore($rows);
        }

        $superAdminRoleId = $roleIds['super_admin'] ?? DB::table('roles')->where('slug', 'super_admin')->value('id');

        DB::table('users')
            ->where('employee_code', 'EMP09999')
            ->update([
                'role_id' => $superAdminRoleId,
                'data_scope' => null,
                'updated_at' => $now,
            ]);

        $itDepartmentId = DB::table('departments')->where('code', 'IT')->value('id');
        $itPermissionKeys = ['tickets.manage', 'it.portal.view'];

        if ($itDepartmentId) {
            $itUserIds = DB::table('employees')
                ->where('department_id', $itDepartmentId)
                ->pluck('user_id');

            $rows = $itUserIds
                ->flatMap(fn (int $userId) => collect($itPermissionKeys)->map(fn (string $permissionKey) => [
                    'user_id' => $userId,
                    'permission_id' => $permissionIds[$permissionKey],
                    'effect' => 'grant',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))
                ->all();

            if ($rows !== []) {
                DB::table('permission_user')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('data_scope');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('default_data_scope');
        });
    }
};
