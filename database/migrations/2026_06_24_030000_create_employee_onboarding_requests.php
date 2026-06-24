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
        if (! Schema::hasTable('employee_onboarding_requests')) {
            Schema::create('employee_onboarding_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requested_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('it_completed_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('hr_approved_by')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('user_id')->nullable()->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('directory_entry_id')->nullable()->references('id')->on('employee_directory_entries')->cascadeOnUpdate()->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
                $table->string('employee_code')->unique();
                $table->string('english_name');
                $table->string('thai_name')->nullable();
                $table->string('english_nickname')->nullable();
                $table->string('thai_nickname')->nullable();
                $table->string('position')->nullable();
                $table->string('business_unit')->nullable();
                $table->string('team')->nullable();
                $table->string('location')->nullable();
                $table->string('corporate_email')->nullable();
                $table->string('personal_phone')->nullable();
                $table->string('extension_number')->nullable();
                $table->date('start_date')->nullable();
                $table->string('photo_path')->nullable();
                $table->string('status')->default('pending_it');
                $table->text('hr_note')->nullable();
                $table->text('it_note')->nullable();
                $table->timestamp('it_completed_at')->nullable();
                $table->timestamp('hr_approved_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'start_date']);
            });
        }

        if (! Schema::hasTable('employee_onboarding_systems')) {
            Schema::create('employee_onboarding_systems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_onboarding_request_id');
                $table->foreignId('it_asset_id')->nullable();
                $table->string('system_name');
                $table->string('requested_access')->nullable();
                $table->string('username')->nullable();
                $table->string('email')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('employee_onboarding_request_id', 'eos_request_fk')
                    ->references('id')
                    ->on('employee_onboarding_requests')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreign('it_asset_id', 'eos_asset_fk')
                    ->references('id')
                    ->on('it_assets')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('employee_directory_entries')) {
            Schema::table('employee_directory_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_directory_entries', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('id')->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                }

                if (! Schema::hasColumn('employee_directory_entries', 'employment_status')) {
                    $table->string('employment_status')->default('active')->after('entry_type');
                }

                if (! Schema::hasColumn('employee_directory_entries', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('is_active');
                }

                if (! Schema::hasColumn('employee_directory_entries', 'resigned_at')) {
                    $table->timestamp('resigned_at')->nullable()->after('published_at');
                }
            });
        }

        if (Schema::hasTable('permissions')) {
            $now = now();
            $permissions = collect(Permission::CATALOG)
                ->whereIn('key', [
                    'assets.view',
                    'assets.manage',
                    'assets.reports',
                    'assets.settings.manage',
                    'assets.delete',
                    'hr.onboarding.manage',
                    'it.onboarding.manage',
                ])
                ->map(fn (array $permission) => [
                    ...$permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all();

            DB::table('permissions')->upsert($permissions, ['key'], ['group', 'name', 'description', 'sort_order', 'updated_at']);

            $permissionIds = DB::table('permissions')
                ->whereIn('key', ['hr.onboarding.manage', 'it.onboarding.manage'])
                ->pluck('id', 'key');
            $roleIds = DB::table('roles')->whereIn('slug', ['hr', 'it_asset_officer', 'it_asset_admin', 'admin', 'super_admin'])->pluck('id', 'slug');

            $rolePermissions = [
                'hr' => ['hr.onboarding.manage'],
                'admin' => ['hr.onboarding.manage', 'it.onboarding.manage'],
                'super_admin' => ['hr.onboarding.manage', 'it.onboarding.manage'],
                'it_asset_officer' => ['it.onboarding.manage'],
                'it_asset_admin' => ['it.onboarding.manage'],
            ];

            foreach ($rolePermissions as $roleSlug => $keys) {
                $roleId = $roleIds[$roleSlug] ?? null;

                if (! $roleId) {
                    continue;
                }

                $rows = collect($keys)
                    ->map(fn (string $key) => $permissionIds[$key] ?? null)
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

            $itDepartmentId = DB::table('departments')->where('code', 'IT')->value('id');
            $itPermissionId = $permissionIds['it.onboarding.manage'] ?? null;

            if ($itDepartmentId && $itPermissionId) {
                $rows = DB::table('employees')
                    ->where('department_id', $itDepartmentId)
                    ->pluck('user_id')
                    ->map(fn (int $userId) => [
                        'user_id' => $userId,
                        'permission_id' => $itPermissionId,
                        'effect' => 'grant',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                DB::table('permission_user')->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_directory_entries')) {
            if (Schema::hasColumn('employee_directory_entries', 'user_id')) {
                Schema::table('employee_directory_entries', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            }

            Schema::table('employee_directory_entries', function (Blueprint $table) {
                foreach (['resigned_at', 'published_at', 'employment_status', 'user_id'] as $column) {
                    if (Schema::hasColumn('employee_directory_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('employee_onboarding_systems');
        Schema::dropIfExists('employee_onboarding_requests');
    }
};
