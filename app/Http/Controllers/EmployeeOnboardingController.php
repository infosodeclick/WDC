<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOnboardingRequest;
use App\Models\EmployeeOnboardingSystem;
use App\Models\ItAsset;
use App\Models\Role;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'onboarding' => $onboarding->load('department', 'systems.asset', 'systems.provisioner', 'requester', 'claimedBy', 'itCompleter', 'hrApprover', 'cancelRequester', 'cancelConfirmer'),
            'canManageItOnboarding' => $this->canManageItOnboarding($actor) && ! in_array($onboarding->status, ['hr_approved', 'cancelled'], true),
            'availableAssets' => $actor->canManageItAssets()
                ? ItAsset::whereIn('status', ['active', 'repair'])->orderBy('code')->get()
                : collect(),
        ]);
    }

    public function exportItChecklist(Request $request): StreamedResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageItOnboarding($actor), 403);

        $rows = $this->onboardingExportRows(
            EmployeeOnboardingRequest::with([
                'department',
                'claimedBy',
                'cancelRequester',
                'cancelConfirmer',
                'itCompleter',
                'systems.asset',
                'systems.provisioner',
            ])
                ->latest()
                ->get()
        );

        return $request->string('format')->lower()->toString() === 'csv'
            ? $this->streamOnboardingCsv($rows)
            : $this->streamOnboardingExcel($rows);
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

    public function claim(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageItOnboarding($actor), 403);
        abort_if(in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true), 422);

        $updated = EmployeeOnboardingRequest::query()
            ->whereKey($onboarding->id)
            ->where(function ($query) use ($actor): void {
                $query->whereNull('claimed_by_id')
                    ->orWhere('claimed_by_id', $actor->id);
            })
            ->update([
                'claimed_by_id' => $actor->id,
                'claimed_at' => now(),
                'status' => 'in_progress',
                'updated_at' => now(),
            ]);

        if (! $updated) {
            $owner = $onboarding->fresh('claimedBy')?->claimedBy?->name ?? 'ทีม IT คนอื่น';

            return back()->withErrors("รายการนี้มี {$owner} รับงานอยู่แล้ว");
        }

        $this->log($request, 'claim_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Claimed IT onboarding {$onboarding->employee_code}");

        return back()->with('status', 'รับงานนี้แล้ว สามารถบันทึก checklist ได้');
    }

    public function release(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageItOnboarding($actor), 403);
        abort_if(in_array($onboarding->status, ['it_completed', 'hr_approved', 'cancel_requested', 'cancelled'], true), 422);
        abort_unless($this->canWorkOnClaim($onboarding->fresh(), $actor), 403);

        $onboarding->update([
            'claimed_by_id' => null,
            'claimed_at' => null,
            'status' => 'pending_it',
        ]);

        $this->log($request, 'release_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Released IT onboarding {$onboarding->employee_code}");

        return back()->with('status', 'ปล่อยงานกลับเข้าคิว IT แล้ว');
    }

    public function updateIt(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']), 403);
        abort_if(in_array($onboarding->status, ['hr_approved', 'cancel_requested', 'cancelled'], true), 422);
        abort_unless($this->claimIfAvailable($onboarding, $actor), 403);

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

            $newStatus = $systemData['status'] ?? $system->status;
            $provisionedAt = $system->provisioned_at;
            $provisionedById = $system->provisioned_by_id;

            if ($newStatus === 'provisioned' && $system->status !== 'provisioned') {
                $provisionedAt = now();
                $provisionedById = $actor->id;
            }

            if ($newStatus === 'pending') {
                $provisionedAt = null;
                $provisionedById = null;
            }

            $system->update([
                'status' => $newStatus,
                'username' => $system->system_name === 'WDC Portal'
                    ? $onboarding->employee_code
                    : ($systemData['username'] ?? null),
                'email' => $systemData['email'] ?? null,
                'it_asset_id' => $systemData['it_asset_id'] ?? null,
                'notes' => $systemData['notes'] ?? null,
                'provisioned_by_id' => $provisionedById,
                'provisioned_at' => $provisionedAt,
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
        abort_if(in_array($onboarding->status, ['hr_approved', 'cancel_requested', 'cancelled'], true), 422);
        abort_unless($this->claimIfAvailable($onboarding, $actor), 403);

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
            'claimed_by_id' => $actor->id,
            'claimed_at' => $onboarding->claimed_at ?: now(),
        ]);

        $this->notifyUsers(['hr.onboarding.manage', 'hr.employees.manage'], 'IT เปิดระบบพนักงานใหม่เรียบร้อยแล้ว', $onboarding->displayName(), route('onboarding.show', $onboarding));
        $this->log($request, 'complete_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Completed IT onboarding {$onboarding->employee_code}");

        return back()->with('status', 'แจ้ง HR ว่าเปิดระบบเรียบร้อยแล้ว');
    }

    public function cancel(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']), 403);
        abort_if(in_array($onboarding->status, ['hr_approved', 'cancelled'], true), 422);

        $onboarding->load('systems');
        $itStarted = $onboarding->hasItStarted();

        $rules = [
            'cancel_reason' => ['required', 'string', 'max:3000'],
        ];

        if ($itStarted) {
            $rules['cancel_acknowledged'] = ['accepted'];
        }

        $data = $request->validate($rules);
        $now = now();

        if ($itStarted) {
            $onboarding->update([
                'status' => 'cancel_requested',
                'cancel_reason' => $data['cancel_reason'],
                'cancel_requested_by' => $actor->id,
                'cancel_requested_at' => $now,
            ]);

            $this->notifyUsers(['it.onboarding.manage', 'it.portal.view', 'tickets.manage'], 'HR ขอให้ยกเลิกคำขอพนักงานใหม่', $onboarding->displayName(), route('onboarding.show', $onboarding));
            $this->log($request, 'request_cancel_employee_onboarding', EmployeeOnboardingRequest::class, $onboarding->id, "Requested onboarding cancellation {$onboarding->employee_code}");

            return back()->with('status', 'ส่งคำขอยกเลิกให้ IT ตรวจสอบแล้ว');
        }

        $onboarding->update([
            'status' => 'cancelled',
            'cancel_reason' => $data['cancel_reason'],
            'cancel_requested_by' => $actor->id,
            'cancel_requested_at' => $now,
            'cancel_confirmed_by' => $actor->id,
            'cancel_confirmed_at' => $now,
            'cancelled_at' => $now,
        ]);

        $this->notifyUsers(['it.onboarding.manage', 'it.portal.view', 'tickets.manage'], 'HR ยกเลิกคำขอพนักงานใหม่แล้ว', $onboarding->displayName(), route('onboarding.show', $onboarding));
        $this->log($request, 'cancel_employee_onboarding', EmployeeOnboardingRequest::class, $onboarding->id, "Cancelled onboarding {$onboarding->employee_code}");

        return back()->with('status', 'ยกเลิกคำขอพนักงานใหม่แล้ว');
    }

    public function confirmCancel(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageItOnboarding($actor), 403);
        abort_unless($onboarding->status === 'cancel_requested', 422);
        abort_unless($this->claimIfAvailable($onboarding, $actor), 403);

        $data = $request->validate([
            'it_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $onboarding->load('systems.asset');

        foreach ($onboarding->systems as $system) {
            if (! $system->asset) {
                continue;
            }

            $system->asset->update([
                'owner_id' => null,
                'owner_name' => null,
                'status' => 'active',
            ]);
        }

        $onboarding->update([
            'status' => 'cancelled',
            'it_note' => $data['it_note'] ?? $onboarding->it_note,
            'cancel_confirmed_by' => $actor->id,
            'cancel_confirmed_at' => now(),
            'cancelled_at' => now(),
            'claimed_by_id' => $actor->id,
            'claimed_at' => $onboarding->claimed_at ?: now(),
        ]);

        $this->notifyUsers(['hr.onboarding.manage', 'hr.employees.manage'], 'IT ตรวจสอบและยืนยันการยกเลิกแล้ว', $onboarding->displayName(), route('onboarding.show', $onboarding));
        $this->log($request, 'confirm_cancel_employee_onboarding_it', EmployeeOnboardingRequest::class, $onboarding->id, "Confirmed onboarding cancellation {$onboarding->employee_code}");

        return back()->with('status', 'ยืนยันการยกเลิกและแจ้งกลับ HR แล้ว');
    }

    public function publish(EmployeeOnboardingRequest $onboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']), 403);
        abort_unless($onboarding->status === 'it_completed', 422);

        $data = $request->validate([
            'photo' => ['nullable', 'image', 'max:4096'],
            'hr_note' => ['nullable', 'string', 'max:3000'],
            'published_at' => ['nullable', 'date'],
        ]);

        $publishedAt = filled($data['published_at'] ?? null)
            ? Carbon::parse($data['published_at'])->startOfDay()
            : now();

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
                    'published_at' => $publishedAt->toDateString(),
                    'source' => 'hr_onboarding',
                ],
                'is_active' => true,
                'published_at' => $publishedAt,
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

    private function claimIfAvailable(EmployeeOnboardingRequest $onboarding, User $actor): bool
    {
        $onboarding->refresh();

        if ($onboarding->claimed_by_id === $actor->id) {
            return true;
        }

        if ($onboarding->claimed_by_id) {
            return false;
        }

        return (bool) EmployeeOnboardingRequest::query()
            ->whereKey($onboarding->id)
            ->whereNull('claimed_by_id')
            ->update([
                'claimed_by_id' => $actor->id,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function canWorkOnClaim(EmployeeOnboardingRequest $onboarding, User $actor): bool
    {
        return $onboarding->claimed_by_id === null
            || $onboarding->claimed_by_id === $actor->id
            || $actor->canAccessAny(['admin.system.manage', 'admin.users.manage']);
    }

    private function onboardingExportRows(Collection $requests): Collection
    {
        $systemColumns = [
            'AD' => ['ad', 'active directory'],
            'E-Mail' => ['email', 'e-mail', 'mail'],
            'Printer access' => ['printer', 'printer access'],
            'E-memo/smartflow' => ['e-memo', 'ememo', 'smartflow', 'e-memo/smartflow'],
            'AI CRM' => ['ai crm', 'aicrm'],
            'SAP B1' => ['sap b1', 'sap'],
            'Status เครื่อง' => ['status เครื่อง', 'machine status'],
            'Mail welcome' => ['mail welcome', 'welcome mail'],
            'Telephone Directory' => ['telephone directory', 'phone directory'],
        ];

        return $requests->map(function (EmployeeOnboardingRequest $request) use ($systemColumns): array {
            $systems = $request->systems->keyBy(fn (EmployeeOnboardingSystem $system): string => Str::lower(trim($system->system_name)));
            $row = [
                'Department' => $request->department?->name ?? $request->business_unit,
                'Staff ID' => $request->employee_code,
                'ชื่อ-สกุล (Eng)' => $request->english_name,
                'ชื่อ-นามสกุล (ไทย)' => $request->thai_name,
                'ชื่อเล่น' => $request->thai_nickname ?: $request->english_nickname,
                'ตำแหน่ง' => $request->position,
                'ทีม' => $request->team,
                'สาขา' => $request->location,
                'อุปกรณ์สำนักงาน' => $this->assetSummary($request),
                'WDC Portal user' => $request->employee_code,
                'คนรับงาน IT' => $request->claimedBy?->name,
                'เวลารับงาน IT' => $request->claimed_at?->format('d/m/Y H:i'),
            ];

            foreach ($systemColumns as $column => $aliases) {
                $system = $this->findExportSystem($systems, $aliases);
                $row[$column] = $system?->status === 'provisioned' ? 'TRUE' : 'FALSE';
                $row["{$column} by"] = $system?->provisioner?->name;
                $row["{$column} at"] = $system?->provisioned_at?->format('d/m/Y H:i');
            }

            $row['Remark'] = $request->it_note;
            $row['Cancel reason'] = $request->cancel_reason;
            $row['Cancel requested by'] = $request->cancelRequester?->name;
            $row['Cancel requested at'] = $request->cancel_requested_at?->format('d/m/Y H:i');
            $row['Cancel confirmed by'] = $request->cancelConfirmer?->name;
            $row['Cancel confirmed at'] = $request->cancel_confirmed_at?->format('d/m/Y H:i');
            $row['สถานะงาน'] = $request->statusLabel();
            $row['ส่งกลับ HR โดย'] = $request->itCompleter?->name;
            $row['ส่งกลับ HR เมื่อ'] = $request->it_completed_at?->format('d/m/Y H:i');

            return $row;
        });
    }

    private function findExportSystem(Collection $systems, array $aliases): ?EmployeeOnboardingSystem
    {
        foreach ($aliases as $alias) {
            $key = Str::lower(trim($alias));

            if ($systems->has($key)) {
                return $systems->get($key);
            }
        }

        return $systems->first(function (EmployeeOnboardingSystem $system) use ($aliases): bool {
            $name = Str::lower($system->system_name);

            return collect($aliases)->contains(fn (string $alias): bool => Str::contains($name, Str::lower($alias)));
        });
    }

    private function assetSummary(EmployeeOnboardingRequest $request): string
    {
        return $request->systems
            ->pluck('asset')
            ->filter()
            ->map(fn (ItAsset $asset): string => trim("{$asset->code} {$asset->name}"))
            ->filter()
            ->join(', ');
    }

    private function streamOnboardingCsv(Collection $rows): StreamedResponse
    {
        $filename = 'wdc-it-onboarding-checklist-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_keys($rows->first() ?? []));

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function streamOnboardingExcel(Collection $rows): StreamedResponse
    {
        $filename = 'wdc-it-onboarding-checklist-'.now()->format('Ymd-His').'.xls';

        return response()->streamDownload(function () use ($rows): void {
            echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
            echo '<thead><tr>';
            foreach (array_keys($rows->first() ?? []) as $header) {
                echo '<th>'.e($header).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $value) {
                    echo '<td>'.e((string) ($value ?? '')).'</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table></body></html>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function notifyUsers(array $permissionKeys, string $title, string $body, string $url): void
    {
        $recipients = User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccessAny($permissionKeys));

        $notifier = app(PortalNotificationService::class);
        $payload = [
            'type' => 'onboarding',
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ];

        $notifier->createForUsers($recipients, $payload);

        $administrator = User::where('employee_code', 'administrator')
            ->where('is_active', true)
            ->first();

        if ($administrator && ! $recipients->contains('id', $administrator->id)) {
            $notifier->createForUser($administrator, $payload);
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
