<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canOpenAdmin($actor), 403);

        $userSearch = trim($request->string('q')->toString());
        $roleFilter = $request->string('role')->toString();
        $statusFilter = $request->string('status')->toString();
        $usersQuery = User::with('role.permissions', 'employee.department', 'permissionOverrides')->orderBy('employee_code');

        if ($userSearch !== '') {
            $usersQuery->where(function ($query) use ($userSearch) {
                $query->where('employee_code', 'like', "%{$userSearch}%")
                    ->orWhere('name', 'like', "%{$userSearch}%")
                    ->orWhere('email', 'like', "%{$userSearch}%")
                    ->orWhereHas('employee.department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', "%{$userSearch}%"))
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('position', 'like', "%{$userSearch}%"));
            });
        }

        if ($roleFilter !== '') {
            $usersQuery->whereHas('role', fn ($query) => $query->where('slug', $roleFilter));
        }

        if (in_array($statusFilter, ['active', 'suspended'], true)) {
            $usersQuery->where('is_active', $statusFilter === 'active');
        } else {
            $statusFilter = '';
        }

        $allRoles = Role::withCount('users')->with('permissions')->orderBy('id')->get();
        $allPermissions = Permission::orderBy('sort_order')->get();
        $adminAccessKeys = ['admin.users.manage', 'admin.roles.manage', 'admin.activity.view', 'admin.system.manage'];
        $adminCapableUsers = User::with('role.permissions', 'permissionOverrides')
            ->get()
            ->filter(fn (User $user) => $user->canAccessAny($adminAccessKeys))
            ->count();

        return view('admin.index', [
            'users' => $usersQuery->get(),
            'roles' => $allRoles,
            'permissions' => $allPermissions->groupBy('group'),
            'allPermissions' => $allPermissions,
            'menuPermissions' => $this->sidebarMenuPermissions(),
            'scopeLabels' => Permission::DATA_SCOPE_LABELS,
            'departments' => Department::orderBy('name')->get(),
            'logs' => $actor->canAccess('admin.activity.view') ? ActivityLog::with('user')->latest()->take(40)->get() : collect(),
            'userSearch' => $userSearch,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'totalUsers' => User::count(),
            'activeUsers' => User::where('is_active', true)->count(),
            'suspendedUsers' => User::where('is_active', false)->count(),
            'adminCapableUsers' => $adminCapableUsers,
            'canManageUsers' => $actor->canAccess('admin.users.manage'),
            'canManageRoles' => $actor->canAccess('admin.roles.manage'),
            'canViewLogs' => $actor->canAccess('admin.activity.view'),
            'canManageSystem' => $actor->canAccess('admin.system.manage'),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccess('admin.users.manage'), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'data_scope' => ['nullable', Rule::in(array_keys(Permission::DATA_SCOPE_LABELS))],
            'department_id' => ['required', 'exists:departments,id'],
            'position' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $this->ensureRoleAssignable($actor, $role);

        $user = User::create([
            'employee_code' => $data['employee_code'],
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'data_scope' => $data['data_scope'] ?? null,
            'is_active' => true,
        ]);

        Employee::create([
            'user_id' => $user->id,
            'department_id' => $data['department_id'],
            'position' => $data['position'],
            'phone' => $data['phone'] ?? null,
            'start_date' => $data['start_date'] ?? null,
        ]);

        $this->log($request, 'create_user', User::class, $user->id, "Created {$user->employee_code}");

        return back()->with('status', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function updateUser(User $user, Request $request): RedirectResponse
    {
        return $this->updateUserAccess($user, $request);
    }

    public function updateUserAccess(User $user, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccess('admin.users.manage'), 403);
        $this->ensureUserEditable($actor, $user->load('role'));

        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'data_scope' => ['nullable', Rule::in(array_keys(Permission::DATA_SCOPE_LABELS))],
            'is_active' => ['nullable', 'boolean'],
            'permission_grants' => ['nullable', 'array'],
            'permission_grants.*' => ['string', Rule::in(Permission::catalogKeys())],
            'permission_denies' => ['nullable', 'array'],
            'permission_denies.*' => ['string', Rule::in(Permission::catalogKeys())],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $this->ensureRoleAssignable($actor, $role);

        if ($actor->id === $user->id && $user->isSuperAdmin() && ! $role->isSuperAdmin()) {
            return back()->withErrors(['role_id' => 'ไม่สามารถลดสิทธิ์ Super Admin ของบัญชีตนเองได้']);
        }

        if ($actor->id === $user->id && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'ไม่สามารถระงับบัญชีของตนเองได้']);
        }

        $user->update([
            'role_id' => $role->id,
            'data_scope' => $data['data_scope'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($actor->canAccess('admin.roles.manage')) {
            $this->syncUserPermissionOverrides(
                $user,
                $data['permission_grants'] ?? [],
                $data['permission_denies'] ?? [],
            );
        }

        $this->log($request, 'update_user_access', User::class, $user->id, "Updated access for {$user->employee_code}");

        return back()->with('status', 'อัปเดตสิทธิ์ผู้ใช้งานแล้ว');
    }

    public function updateRolePermissions(Role $role, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccess('admin.roles.manage'), 403);

        $data = $request->validate([
            'default_data_scope' => ['required', Rule::in(array_keys(Permission::DATA_SCOPE_LABELS))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::catalogKeys())],
        ]);

        $permissionKeys = $role->isSuperAdmin()
            ? Permission::catalogKeys()
            : ($data['permissions'] ?? []);

        $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->all();

        $role->update(['default_data_scope' => $data['default_data_scope']]);
        $role->permissions()->sync($permissionIds);

        $this->log($request, 'update_role_permissions', Role::class, $role->id, "Updated role {$role->slug}");

        return back()->with('status', 'อัปเดต role template แล้ว');
    }

    private function canOpenAdmin(User $user): bool
    {
        return $user->canAccessAny([
            'admin.users.manage',
            'admin.roles.manage',
            'admin.activity.view',
            'admin.system.manage',
        ]);
    }

    private function sidebarMenuPermissions(): array
    {
        return [
            ['label' => 'หน้าแรก', 'permissions' => ['portal.dashboard.view']],
            ['label' => 'โปรไฟล์พนักงาน', 'permissions' => ['profile.view']],
            ['label' => 'รายชื่อพนักงาน', 'permissions' => ['directory.view']],
            ['label' => 'ห้องประชุม', 'permissions' => ['meeting_rooms.view']],
            ['label' => 'ประกาศ', 'permissions' => ['announcements.view']],
            ['label' => 'เทรนนิ่ง', 'permissions' => ['knowledge.view']],
            ['label' => 'แจ้งปัญหา IT', 'permissions' => ['tickets.create', 'tickets.manage']],
            ['label' => 'คำขอ/อนุมัติ', 'permissions' => ['workflows.create', 'workflows.manage']],
            ['label' => 'ร้องเรียน', 'permissions' => ['complaints.create', 'complaints.review']],
            ['label' => 'แบบฟอร์ม', 'permissions' => ['documents.view']],
            ['label' => 'ศูนย์ IT', 'permissions' => ['it.portal.view', 'tickets.manage']],
            ['label' => 'ทรัพย์สิน IT', 'permissions' => ['assets.view', 'assets.manage', 'assets.reports']],
            ['label' => 'HR Portal', 'permissions' => ['hr.portal.view', 'hr.employees.manage', 'hr.announcements.manage', 'complaints.review']],
            ['label' => 'Admin', 'permissions' => ['admin.users.manage', 'admin.roles.manage', 'admin.activity.view', 'admin.system.manage']],
        ];
    }

    private function ensureRoleAssignable(User $actor, Role $role): void
    {
        abort_if($role->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
    }

    private function ensureUserEditable(User $actor, User $target): void
    {
        abort_if($target->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
    }

    private function syncUserPermissionOverrides(User $user, array $grants, array $denies): void
    {
        $grantKeys = collect($grants)->unique();
        $denyKeys = collect($denies)->unique()->diff($grantKeys);
        $permissionIds = Permission::whereIn('key', $grantKeys->merge($denyKeys)->all())->pluck('id', 'key');
        $syncPayload = [];

        foreach ($grantKeys as $permissionKey) {
            if (isset($permissionIds[$permissionKey])) {
                $syncPayload[$permissionIds[$permissionKey]] = ['effect' => 'grant'];
            }
        }

        foreach ($denyKeys as $permissionKey) {
            if (isset($permissionIds[$permissionKey])) {
                $syncPayload[$permissionIds[$permissionKey]] = ['effect' => 'deny'];
            }
        }

        $user->permissionOverrides()->sync($syncPayload);
    }

    private function log(Request $request, string $action, ?string $subjectType = null, ?int $subjectId = null, ?string $description = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
