<?php

namespace App\Http\Controllers;

use App\Mail\PortalNotificationMail;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOnboardingRequest;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DirectoryUserSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canOpenAdmin($actor), 403);

        $memberSearch = trim($request->string('q')->toString());

        $usersQuery = User::with('role.permissions', 'employee.department', 'permissionOverrides')
            ->whereNotIn('employee_code', ['administrator', 'EMP09999'])
            ->when($memberSearch !== '', function ($query) use ($memberSearch) {
                $query->where(function ($query) use ($memberSearch) {
                    $query->where('employee_code', 'like', "%{$memberSearch}%")
                        ->orWhere('name', 'like', "%{$memberSearch}%")
                        ->orWhere('email', 'like', "%{$memberSearch}%")
                        ->orWhereHas('role', fn ($query) => $query->where('name', 'like', "%{$memberSearch}%"))
                        ->orWhereHas('employee', function ($query) use ($memberSearch) {
                            $query->where('english_name', 'like', "%{$memberSearch}%")
                                ->orWhere('thai_name', 'like', "%{$memberSearch}%")
                                ->orWhere('nickname', 'like', "%{$memberSearch}%")
                                ->orWhere('english_nickname', 'like', "%{$memberSearch}%")
                                ->orWhere('thai_nickname', 'like', "%{$memberSearch}%")
                                ->orWhere('position', 'like', "%{$memberSearch}%")
                                ->orWhere('phone', 'like', "%{$memberSearch}%")
                                ->orWhere('extension_number', 'like', "%{$memberSearch}%");
                        })
                        ->orWhereHas('employee.department', fn ($query) => $query->where('name', 'like', "%{$memberSearch}%"));
                });
            })
            ->orderBy('employee_code');

        $allRoles = Role::withCount('users')
            ->with('permissions')
            ->orderByRaw("CASE slug
                WHEN 'employee' THEN 10
                WHEN 'hr' THEN 20
                WHEN 'it_supervisor' THEN 30
                WHEN 'it_support' THEN 40
                WHEN 'admin' THEN 50
                WHEN 'super_admin' THEN 60
                WHEN 'auditor' THEN 70
                ELSE 999
            END")
            ->orderBy('name')
            ->get();
        $allPermissions = Permission::orderBy('sort_order')->get();
        $directoryManageKeys = ['directory.manage', 'hr.employees.manage'];
        $canManageUsers = $actor->canAccessAny(['admin.users.manage', 'iam.users.manage']);
        $canManageDirectory = $actor->canAccessAny($directoryManageKeys);
        $canCreateUsers = $actor->canAccessAny(['admin.users.manage', 'iam.users.manage', ...$directoryManageKeys]);
        $canManageRoles = $actor->canAccessAny(['admin.roles.manage', 'iam.roles.manage']);
        $canViewLogs = $actor->canAccessAny(['admin.activity.view', 'audit.logs.view']);
        $adminSections = array_values(array_filter([
            ['key' => 'system', 'label' => 'ระบบ', 'icon' => 'bi-diagram-3', 'show' => true],
            ['key' => 'permissions', 'label' => 'กำหนดสิทธิ์', 'icon' => 'bi-shield-check', 'show' => $canManageUsers || $canManageRoles || $canManageDirectory],
            ['key' => 'create-user', 'label' => 'เพิ่มผู้ใช้งาน', 'icon' => 'bi-person-plus', 'show' => $canCreateUsers],
            ['key' => 'notifications', 'label' => 'แจ้งเตือน', 'icon' => 'bi-bell', 'show' => true],
            ['key' => 'role-template', 'label' => 'Role Template', 'icon' => 'bi-sliders', 'show' => $canManageRoles],
            ['key' => 'activity-logs', 'label' => 'Activity Logs', 'icon' => 'bi-clock-history', 'show' => $canViewLogs],
        ], fn (array $section) => $section['show']));
        $sectionAliases = [
            'users' => 'create-user',
            'roles' => 'role-template',
            'activity' => 'activity-logs',
        ];
        $requestedSection = $request->string('section')->toString();
        $activeSection = $sectionAliases[$requestedSection] ?? $requestedSection;

        if (! collect($adminSections)->pluck('key')->contains($activeSection)) {
            $activeSection = $adminSections[0]['key'];
        }

        return view('admin.index', [
            'users' => $usersQuery->get(),
            'memberSearch' => $memberSearch,
            'roles' => $allRoles,
            'permissions' => $allPermissions->groupBy('group'),
            'allPermissions' => $allPermissions,
            'menuPermissions' => $this->sidebarMenuPermissions(),
            'adminSections' => $adminSections,
            'activeSection' => $activeSection,
            'scopeLabels' => Permission::DATA_SCOPE_LABELS,
            'departments' => Department::orderBy('name')->get(),
            'adminNotifications' => Notification::where('user_id', $actor->id)->latest()->take(40)->get(),
            'mailStatus' => $this->mailStatus(),
            'pendingAdminOnboardingRequests' => EmployeeOnboardingRequest::with('department', 'systems')
                ->whereIn('status', ['pending_it', 'in_progress', 'cancel_requested'])
                ->latest()
                ->take(20)
                ->get(),
            'logs' => $canViewLogs ? ActivityLog::with('user')->latest()->take(40)->get() : collect(),
            'canManageUsers' => $canManageUsers,
            'canManageDirectory' => $canManageDirectory,
            'canCreateUsers' => $canCreateUsers,
            'canManageRoles' => $canManageRoles,
            'canViewLogs' => $canViewLogs,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['admin.users.manage', 'iam.users.manage', 'directory.manage', 'hr.employees.manage']), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'data_scope' => ['nullable', Rule::in(array_keys(Permission::DATA_SCOPE_LABELS))],
            'department_id' => ['required', 'exists:departments,id'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'thai_name' => ['nullable', 'string', 'max:255'],
            'english_nickname' => ['nullable', 'string', 'max:120'],
            'thai_nickname' => ['nullable', 'string', 'max:120'],
            'position' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'extension_number' => ['nullable', 'string', 'max:50'],
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
            'english_name' => $data['english_name'] ?? null,
            'thai_name' => $data['thai_name'] ?? $data['name'],
            'nickname' => $data['thai_nickname'] ?? $data['english_nickname'] ?? null,
            'english_nickname' => $data['english_nickname'] ?? null,
            'thai_nickname' => $data['thai_nickname'] ?? null,
            'position' => $data['position'],
            'phone' => $data['phone'] ?? null,
            'extension_number' => $data['extension_number'] ?? null,
            'start_date' => $data['start_date'] ?? null,
        ]);

        $this->syncDirectoryEntry($user->load('employee.department'));

        $this->log($request, 'create_user', User::class, $user->id, "Created {$user->employee_code}");

        return back()->with('status', 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว');
    }

    private function mailStatus(): array
    {
        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.smtp.host');
        $port = (string) config('mail.mailers.smtp.port');
        $scheme = strtolower((string) config('mail.mailers.smtp.scheme'));
        $usernameConfigured = filled(config('mail.mailers.smtp.username'));
        $passwordConfigured = filled(config('mail.mailers.smtp.password'));
        $fromAddress = (string) config('mail.from.address');
        $notificationsEnabled = (bool) config('wdc.mail_notifications_enabled');
        $schemeConfigured = in_array($scheme, ['smtp', 'smtps', 'tls', 'ssl'], true);

        return [
            'enabled' => $notificationsEnabled,
            'mailer' => $mailer,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'from' => $fromAddress,
            'username_configured' => $usernameConfigured,
            'password_configured' => $passwordConfigured,
            'scheme_configured' => $schemeConfigured,
            'ready' => $notificationsEnabled
                && $mailer === 'smtp'
                && $host !== ''
                && $host !== '127.0.0.1'
                && $schemeConfigured
                && $usernameConfigured
                && $passwordConfigured
                && $fromAddress !== '',
        ];
    }

    public function sendMailTest(Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canOpenAdmin($actor), 403);

        if (! $actor->email) {
            return redirect()
                ->route('admin.index', ['section' => 'notifications'])
                ->withErrors(['mail_test' => 'บัญชีของคุณยังไม่มีอีเมล จึงยังทดสอบส่ง Zoho Mail ไม่ได้']);
        }

        $mailStatus = $this->mailStatus();

        if (! $mailStatus['ready']) {
            return redirect()
                ->route('admin.index', ['section' => 'notifications'])
                ->withErrors(['mail_test' => 'ระบบอีเมลยังไม่พร้อม กรุณาตั้งค่า Zoho SMTP และเปิด WDC_MAIL_NOTIFICATIONS_ENABLED ก่อนทดสอบ']);
        }

        $notification = Notification::create([
            'user_id' => $actor->id,
            'type' => 'mail_test',
            'title' => 'ทดสอบอีเมลจาก WDC Portal',
            'body' => 'ถ้าได้รับอีเมลฉบับนี้ แปลว่าระบบแจ้งเตือนผ่าน Zoho SMTP พร้อมใช้งานแล้ว',
            'url' => route('admin.index', ['section' => 'notifications']),
        ]);

        try {
            Mail::to($actor->email)->send(new PortalNotificationMail($notification));
        } catch (Throwable $exception) {
            Log::warning('WDC admin mail test failed.', [
                'notification_id' => $notification->id,
                'user_id' => $actor->id,
                'email' => $actor->email,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.index', ['section' => 'notifications'])
                ->withErrors(['mail_test' => 'ทดสอบส่งอีเมลไม่สำเร็จ กรุณาตรวจ Zoho SMTP, app password และ Railway variables']);
        }

        $this->log($request, 'send_mail_test', Notification::class, $notification->id, "Sent mail test to {$actor->email}");

        return redirect()
            ->route('admin.index', ['section' => 'notifications'])
            ->with('status', 'ส่งอีเมลทดสอบแล้ว กรุณาตรวจกล่องอีเมลของคุณ');
    }

    public function syncDirectoryUsers(Request $request, DirectoryUserSyncService $syncService): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['admin.users.manage', 'iam.users.manage']), 403);

        $data = $request->validate([
            'employee_sync_file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $stats = $syncService->syncFromXlsx(
                $data['employee_sync_file']->getRealPath(),
                true,
                'Wdc@2026',
            );
        } catch (\Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['employee_sync_file' => 'ไม่สามารถซิงค์ไฟล์นี้ได้: '.$exception->getMessage()]);
        }

        $this->log($request, 'sync_directory_users', User::class, $actor->id, "Synced {$stats['created_users']} directory users from Excel");

        return redirect()
            ->route('admin.index', ['section' => 'permissions'])
            ->with('status', "ซิงค์บัญชีพนักงานแล้ว: สร้างใหม่ {$stats['created_users']} รายการ, อัปเดต {$stats['updated_users']} รายการ")
            ->with('directory_sync_stats', $stats);
    }

    public function updateUser(User $user, Request $request): RedirectResponse
    {
        return $this->updateUserAccess($user, $request);
    }

    public function updateUserAccess(User $user, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        $canManageAccess = $actor->canAccessAny(['admin.users.manage', 'iam.users.manage']);
        $canManageDirectory = $actor->canAccessAny(['directory.manage', 'hr.employees.manage']);
        $canManageRoles = $actor->canAccessAny(['admin.roles.manage', 'iam.roles.manage']);

        abort_unless($canManageAccess || $canManageDirectory || $canManageRoles, 403);
        $this->ensureUserEditable($actor, $user->load('role'));

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['nullable', 'exists:roles,id'],
            'data_scope' => ['nullable', Rule::in(array_keys(Permission::DATA_SCOPE_LABELS))],
            'is_active' => ['nullable', 'boolean'],
            'department_id' => ['sometimes', 'required', 'exists:departments,id'],
            'english_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'thai_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'english_nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'thai_nickname' => ['sometimes', 'nullable', 'string', 'max:120'],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'extension_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'permission_grants' => ['nullable', 'array'],
            'permission_grants.*' => ['string', Rule::in(Permission::catalogKeys())],
            'permission_denies' => ['nullable', 'array'],
            'permission_denies.*' => ['string', Rule::in(Permission::catalogKeys())],
        ]);

        $role = $canManageAccess && ! empty($data['role_id'])
            ? Role::findOrFail($data['role_id'])
            : $user->role;

        $this->ensureRoleAssignable($actor, $role);

        if ($actor->id === $user->id && $user->isSuperAdmin() && ! $role->isSuperAdmin()) {
            return back()->withErrors(['role_id' => 'ไม่สามารถลดสิทธิ์ Super Admin ของบัญชีตนเองได้']);
        }

        if ($actor->id === $user->id && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'ไม่สามารถระงับบัญชีของตนเองได้']);
        }

        $userPayload = [];

        if ($canManageAccess) {
            $userPayload['role_id'] = $role->id;

            if ($request->has('data_scope')) {
                $userPayload['data_scope'] = $data['data_scope'] ?? null;
            }
        }

        if ($canManageAccess || $canManageDirectory) {
            $userPayload['is_active'] = $request->boolean('is_active');
        }

        foreach (['name', 'email'] as $field) {
            if ($request->has($field)) {
                $userPayload[$field] = $data[$field] ?? null;
            }
        }

        $shouldSyncDirectory = false;

        if ($userPayload !== []) {
            $shouldSyncDirectory = array_key_exists('is_active', $userPayload);
            $user->update($userPayload);
        }

        if ($request->hasAny([
            'department_id',
            'english_name',
            'thai_name',
            'english_nickname',
            'thai_nickname',
            'position',
            'phone',
            'extension_number',
            'start_date',
        ])) {
            $employee = $user->employee;
            $fallbackDepartmentId = $employee?->department_id ?: Department::query()->value('id');

            $employeePayload = [
                'department_id' => $request->has('department_id') ? $data['department_id'] : $fallbackDepartmentId,
                'english_name' => $request->has('english_name') ? ($data['english_name'] ?? null) : $employee?->english_name,
                'thai_name' => $request->has('thai_name') ? ($data['thai_name'] ?? null) : $employee?->thai_name,
                'nickname' => $request->has('thai_nickname') || $request->has('english_nickname')
                    ? ($data['thai_nickname'] ?? $data['english_nickname'] ?? null)
                    : $employee?->nickname,
                'english_nickname' => $request->has('english_nickname') ? ($data['english_nickname'] ?? null) : $employee?->english_nickname,
                'thai_nickname' => $request->has('thai_nickname') ? ($data['thai_nickname'] ?? null) : $employee?->thai_nickname,
                'position' => $request->has('position') ? $data['position'] : ($employee?->position ?? '-'),
                'phone' => $request->has('phone') ? ($data['phone'] ?? null) : $employee?->phone,
                'extension_number' => $request->has('extension_number') ? ($data['extension_number'] ?? null) : $employee?->extension_number,
                'start_date' => $request->has('start_date') ? ($data['start_date'] ?? null) : $employee?->start_date,
            ];

            $user->employee()->updateOrCreate(
                ['user_id' => $user->id],
                $employeePayload,
            );

            $shouldSyncDirectory = true;
        }

        if ($shouldSyncDirectory) {
            $this->syncDirectoryEntry($user->fresh(['employee.department']));
        }

        if ($canManageRoles) {
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

        abort_unless($actor->canAccessAny(['admin.roles.manage', 'iam.roles.manage']), 403);

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
            'iam.users.manage',
            'iam.roles.manage',
            'audit.logs.view',
            'audit.logs.export',
            'directory.manage',
            'hr.employees.manage',
        ]);
    }

    private function sidebarMenuPermissions(): array
    {
        return [
            ['label' => 'หน้าแรก', 'permissions' => ['portal.dashboard.view']],
            ['label' => 'โปรไฟล์พนักงาน', 'permissions' => ['profile.view']],
            ['label' => 'รายชื่อพนักงาน', 'permissions' => ['directory.view', 'directory.manage']],
            ['label' => 'ห้องประชุม', 'permissions' => ['meeting_rooms.view']],
            ['label' => 'ประกาศ', 'permissions' => ['announcements.view']],
            ['label' => 'เทรนนิ่ง', 'permissions' => ['knowledge.view']],
            ['label' => 'แจ้งปัญหา IT', 'permissions' => ['tickets.create', 'tickets.manage']],
            ['label' => 'คำขอ/อนุมัติ', 'permissions' => ['workflows.create', 'workflows.manage']],
            ['label' => 'ร้องเรียน', 'permissions' => ['complaints.create', 'complaints.review']],
            ['label' => 'แบบฟอร์ม', 'permissions' => ['documents.view']],
            ['label' => 'IT', 'permissions' => ['it.portal.view', 'tickets.manage', 'it.onboarding.manage']],
            ['label' => 'INVENTORY', 'permissions' => ['assets.view', 'assets.manage', 'assets.reports', 'assets.settings.manage', 'assets.delete']],
            ['label' => 'HR', 'permissions' => ['hr.portal.view', 'hr.onboarding.manage', 'hr.employees.manage', 'hr.announcements.manage', 'complaints.review']],
            ['label' => 'Admin', 'permissions' => ['admin.users.manage', 'admin.roles.manage', 'admin.activity.view', 'admin.system.manage', 'iam.users.manage', 'iam.roles.manage', 'audit.logs.view']],
        ];
    }

    private function ensureRoleAssignable(User $actor, Role $role): void
    {
        abort_if($role->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if (! $actor->canAccess('admin.users.manage')) {
            $role->loadMissing('permissions');

            $restrictedPermissions = [
                'admin.users.manage',
                'admin.roles.manage',
                'admin.activity.view',
                'admin.system.manage',
                'iam.users.manage',
                'iam.roles.manage',
                'system.breakglass.use',
            ];

            abort_if($role->permissions->pluck('key')->intersect($restrictedPermissions)->isNotEmpty(), 403);
        }
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

    private function syncDirectoryEntry(User $user): void
    {
        $user->loadMissing('employee.department');

        if (! $user->employee) {
            return;
        }

        $employee = $user->employee;
        $displayName = $employee->english_name ?: $user->name;
        $thaiName = $employee->thai_name ?: $user->name;

        $entry = null;

        if ($user->email) {
            $entry = EmployeeDirectoryEntry::where('entry_type', 'employee')
                ->where('email', $user->email)
                ->first();
        }

        $entry ??= EmployeeDirectoryEntry::where('source_system', 'wdc')
            ->where('source_record_id', $user->employee_code)
            ->first();

        $payload = [
            'entry_type' => 'employee',
            'display_name' => $displayName,
            'english_name' => $employee->english_name,
            'thai_name' => $thaiName,
            'nickname' => $employee->thai_nickname ?: $employee->english_nickname ?: $employee->nickname,
            'english_nickname' => $employee->english_nickname,
            'thai_nickname' => $employee->thai_nickname,
            'department' => $employee->department?->name,
            'team' => $employee->team,
            'position' => $employee->position,
            'location' => $employee->location,
            'email' => $user->email,
            'phone' => $employee->phone,
            'extension_number' => $employee->extension_number,
            'is_active' => $user->is_active,
            'raw_payload' => [
                ...($entry?->raw_payload ?? []),
                'employee_code' => $user->employee_code,
                'managed_from_admin' => true,
            ],
            'imported_at' => now(),
        ];

        if ($entry) {
            $entry->update($payload);

            return;
        }

        EmployeeDirectoryEntry::create([
            'source_system' => 'wdc',
            'source_record_id' => $user->employee_code,
            ...$payload,
        ]);
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
