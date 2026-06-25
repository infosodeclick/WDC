<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOnboardingRequest;
use App\Models\EmployeeOnboardingSystem;
use App\Models\ItAsset;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeOnboardingController extends Controller
{
    private const DEFAULT_ONBOARDING_SYSTEMS = [
        'WDC Portal',
        'Active Directory',
        'EMAIL',
        'ทรัพย์สิน',
    ];

    public function show(EmployeeOnboardingRequest $onboarding, Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canViewOnboarding($actor), 403);

        return view('onboarding.show', [
            'onboarding' => $onboarding->load('department', 'systems.asset', 'requester', 'itCompleter', 'hrApprover'),
            'canManageItOnboarding' => $this->canManageItOnboarding($actor) && $onboarding->status !== 'hr_approved',
            'availableAssets' => $actor->canManageItAssets()
                ? ItAsset::whereIn('status', ['active', 'repair'])->orderBy('code')->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']), 403);

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:employee_onboarding_requests,employee_code', 'unique:users,employee_code'],
            'english_first_name' => ['nullable', 'required_without:english_name', 'string', 'max:120'],
            'english_last_name' => ['nullable', 'required_without:english_name', 'string', 'max:120'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'thai_first_name' => ['nullable', 'string', 'max:120'],
            'thai_last_name' => ['nullable', 'string', 'max:120'],
            'thai_name' => ['nullable', 'string', 'max:255'],
            'english_nickname' => ['nullable', 'string', 'max:100'],
            'thai_nickname' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'business_unit' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'corporate_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'personal_phone' => ['nullable', 'string', 'max:80'],
            'extension_number' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date'],
            'hr_note' => ['nullable', 'string', 'max:3000'],
            'requested_systems' => ['nullable', 'array'],
            'requested_systems.*' => ['string', 'max:120'],
            'other_systems' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['english_name'] = $this->joinName($data['english_first_name'] ?? null, $data['english_last_name'] ?? null)
            ?: trim((string) ($data['english_name'] ?? ''));
        $data['thai_name'] = $this->joinName($data['thai_first_name'] ?? null, $data['thai_last_name'] ?? null)
            ?: ($data['thai_name'] ?? null);

        if (($data['business_unit'] ?? null) === null && ! empty($data['department_id'])) {
            $data['business_unit'] = Department::whereKey($data['department_id'])->value('name');
        }

        $onboarding = EmployeeOnboardingRequest::create([
            ...collect($data)->except([
                'english_first_name',
                'english_last_name',
                'thai_first_name',
                'thai_last_name',
                'requested_systems',
                'other_systems',
            ])->all(),
            'requested_by' => $actor->id,
            'status' => 'pending_it',
        ]);

        $systemNames = collect(self::DEFAULT_ONBOARDING_SYSTEMS)
            ->merge($data['requested_systems'] ?? [])
            ->merge(collect(preg_split('/[\r\n,]+/', (string) ($data['other_systems'] ?? ''))))
            ->map(fn (string $system) => $this->normalizeSystemName($system))
            ->filter()
            ->unique()
            ->values();

        $systemNames->each(fn (string $systemName) => EmployeeOnboardingSystem::create([
            'employee_onboarding_request_id' => $onboarding->id,
            'system_name' => $systemName,
            'requested_access' => 'เปิดสิทธิ์เริ่มงานใหม่',
            'username' => $systemName === 'WDC Portal' ? $onboarding->employee_code : null,
            'status' => 'pending',
        ]));

        $this->notifyUsers(['it.onboarding.manage', 'it.portal.view'], 'มีรายการพนักงานใหม่จาก HR', $onboarding->displayName(), route('onboarding.show', $onboarding));
        $this->log($request, 'create_employee_onboarding', EmployeeOnboardingRequest::class, $onboarding->id, "Created onboarding {$onboarding->employee_code}");

        return back()->with('status', 'ส่งรายการพนักงานใหม่ให้ IT แล้ว');
    }

    public function updateIt(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']), 403);
        abort_if($onboarding->status === 'hr_approved', 422);

        $data = $request->validate([
            'systems' => ['nullable', 'array'],
            'systems.*.status' => ['nullable', Rule::in(EmployeeOnboardingSystem::STATUSES)],
            'systems.*.username' => ['nullable', 'string', 'max:255'],
            'systems.*.email' => ['nullable', 'email', 'max:255'],
            'systems.*.it_asset_id' => ['nullable', 'exists:it_assets,id'],
            'systems.*.notes' => ['nullable', 'string', 'max:1000'],
            'it_note' => ['nullable', 'string', 'max:3000'],
        ]);

        foreach ($data['systems'] ?? [] as $systemId => $systemData) {
            $system = $onboarding->systems()->whereKey($systemId)->first();

            if (! $system) {
                continue;
            }

            $system->update([
                'status' => $systemData['status'] ?? $system->status,
                'username' => $system->system_name === 'WDC Portal'
                    ? $onboarding->employee_code
                    : ($systemData['username'] ?? null),
                'email' => $systemData['email'] ?? null,
                'it_asset_id' => $systemData['it_asset_id'] ?? null,
                'notes' => $systemData['notes'] ?? null,
            ]);
        }

        $onboarding->update([
            'status' => 'in_progress',
            'it_note' => $data['it_note'] ?? $onboarding->it_note,
        ]);

        $this->log($request, 'update_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Updated onboarding IT checklist {$onboarding->employee_code}");

        return back()->with('status', 'บันทึกข้อมูลเปิดระบบของพนักงานใหม่แล้ว');
    }

    public function completeIt(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']), 403);
        abort_if($onboarding->status === 'hr_approved', 422);

        $onboarding->load('systems.asset');

        foreach ($onboarding->systems as $system) {
            if (! $system->asset) {
                continue;
            }

            $system->asset->update([
                'owner_name' => $onboarding->english_name,
                'department' => $onboarding->department?->name ?? $onboarding->business_unit,
                'status' => 'active',
            ]);
        }

        $onboarding->update([
            'status' => 'it_completed',
            'it_completed_by' => $actor->id,
            'it_completed_at' => now(),
        ]);

        $this->notifyUsers(['hr.onboarding.manage', 'hr.employees.manage'], 'IT เปิดระบบพนักงานใหม่เรียบร้อยแล้ว', $onboarding->displayName(), route('onboarding.show', $onboarding));
        $this->log($request, 'complete_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Completed IT onboarding {$onboarding->employee_code}");

        return back()->with('status', 'แจ้ง HR ว่าเปิดระบบเรียบร้อยแล้ว');
    }

    public function publish(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']), 403);
        abort_unless($onboarding->status === 'it_completed', 422);

        $data = $request->validate([
            'photo' => ['nullable', 'image', 'max:4096'],
            'hr_note' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($request->hasFile('photo')) {
            $onboarding->update([
                'photo_path' => $request->file('photo')->store('employee-photos', 'public'),
            ]);
        }

        $roleId = Role::where('slug', 'employee')->value('id');
        $departmentId = $onboarding->department_id ?: Department::query()->orderBy('id')->value('id');

        $user = User::updateOrCreate(
            ['employee_code' => $onboarding->employee_code],
            [
                'role_id' => $roleId,
                'data_scope' => 'own',
                'name' => $onboarding->english_name,
                'email' => $onboarding->corporate_email,
                'password' => Str::password(18),
                'is_active' => true,
            ],
        );

        $user->employee()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $departmentId,
                'english_name' => $onboarding->english_name,
                'thai_name' => $onboarding->thai_name,
                'nickname' => $onboarding->thai_nickname ?: $onboarding->english_nickname,
                'english_nickname' => $onboarding->english_nickname,
                'thai_nickname' => $onboarding->thai_nickname,
                'position' => $onboarding->position ?: '-',
                'business_unit' => $onboarding->business_unit,
                'team' => $onboarding->team,
                'location' => $onboarding->location,
                'phone' => $onboarding->personal_phone,
                'extension_number' => $onboarding->extension_number,
                'start_date' => $onboarding->start_date,
            ],
        );

        $directory = EmployeeDirectoryEntry::updateOrCreate(
            ['source_system' => 'wdc', 'source_record_id' => $onboarding->employee_code],
            [
                'user_id' => $user->id,
                'entry_type' => 'employee',
                'employment_status' => 'active',
                'display_name' => $onboarding->english_name,
                'english_name' => $onboarding->english_name,
                'thai_name' => $onboarding->thai_name,
                'nickname' => $onboarding->thai_nickname ?: $onboarding->english_nickname,
                'english_nickname' => $onboarding->english_nickname,
                'thai_nickname' => $onboarding->thai_nickname,
                'department' => $onboarding->department?->name ?? $onboarding->business_unit,
                'team' => $onboarding->team,
                'position' => $onboarding->position,
                'location' => $onboarding->location,
                'email' => $onboarding->corporate_email,
                'phone' => $onboarding->personal_phone,
                'extension_number' => $onboarding->extension_number,
                'image_url' => $onboarding->photo_path ? Storage::disk('public')->url($onboarding->photo_path) : null,
                'raw_payload' => [
                    'employee_code' => $onboarding->employee_code,
                    'start_date' => $onboarding->start_date?->toDateString(),
                    'source' => 'hr_onboarding',
                ],
                'is_active' => true,
                'published_at' => now(),
                'resigned_at' => null,
                'imported_at' => now(),
            ],
        );

        $onboarding->update([
            'status' => 'hr_approved',
            'hr_note' => $data['hr_note'] ?? $onboarding->hr_note,
            'hr_approved_by' => $actor->id,
            'hr_approved_at' => now(),
            'user_id' => $user->id,
            'directory_entry_id' => $directory->id,
        ]);

        $onboarding->systems()
            ->with('asset')
            ->get()
            ->each(function (EmployeeOnboardingSystem $system) use ($user, $onboarding) {
                if (! $system->asset) {
                    return;
                }

                $system->asset->update([
                    'owner_id' => $user->id,
                    'owner_name' => $user->name,
                    'department' => $onboarding->department?->name ?? $onboarding->business_unit,
                    'status' => 'active',
                ]);
            });

        $this->log($request, 'publish_employee_onboarding', EmployeeOnboardingRequest::class, $onboarding->id, "Published onboarding {$onboarding->employee_code}");

        return back()->with('status', 'อนุมัติและแสดงพนักงานใหม่ในรายชื่อพนักงานแล้ว');
    }

    private function notifyUsers(array $permissionKeys, string $title, string $body, string $url): void
    {
        $recipients = User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccessAny($permissionKeys));

        $recipients->each(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => 'onboarding',
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]));

        $administrator = User::where('employee_code', 'administrator')
            ->where('is_active', true)
            ->first();

        if ($administrator && ! $recipients->contains('id', $administrator->id)) {
            Notification::create([
                'user_id' => $administrator->id,
                'type' => 'onboarding',
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);
        }
    }

    private function canViewOnboarding(User $user): bool
    {
        return $user->canAccessAny([
            'it.onboarding.manage',
            'it.portal.view',
            'tickets.manage',
            'hr.onboarding.manage',
            'hr.employees.manage',
        ]);
    }

    private function canManageItOnboarding(User $user): bool
    {
        return $user->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']);
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

    private function joinName(?string $firstName, ?string $lastName): string
    {
        return collect([$firstName, $lastName])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->join(' ');
    }

    private function normalizeSystemName(string $system): string
    {
        $system = trim($system);
        $lower = strtolower($system);

        return match ($lower) {
            'email', 'e-mail', 'mail' => 'EMAIL',
            'ad', 'active directory' => 'Active Directory',
            'asset', 'assets', 'inventory', 'it asset', 'it assets' => 'ทรัพย์สิน',
            default => $system,
        };
    }
}
