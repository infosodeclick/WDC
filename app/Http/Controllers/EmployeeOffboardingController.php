<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOffboardingSystem;
use App\Models\EmployeeOnboardingAsset;
use App\Models\ItAsset;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeOffboardingController extends Controller
{
    private const DEFAULT_SYSTEMS = [
        'WDC Portal',
        'Active Directory',
        'EMAIL',
        'Telephone Directory',
    ];

    public function show(EmployeeOffboardingRequest $offboarding, Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canView($actor), 403);

        return view('offboarding.show', [
            'offboarding' => $offboarding->load('systems.asset', 'systems.completer', 'requester', 'claimedBy', 'itCompleter', 'hrApprover', 'employeeUser.employee.department'),
            'canManageItOffboarding' => $this->canManageIt($actor) && $offboarding->status !== 'hr_approved',
            'canManageHrOffboarding' => $actor->canAccessAny(['hr.employees.manage', 'directory.manage']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccess('hr.employees.manage'), 403);

        $data = $request->validate([
            'employee_user_id' => ['required', 'exists:users,id'],
            'resignation_date' => ['nullable', 'date'],
            'hr_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $employee = User::with('employee.department', 'itAssets')
            ->whereKey($data['employee_user_id'])
            ->where('employee_code', '!=', 'administrator')
            ->firstOrFail();

        abort_if($employee->isSuperAdmin(), 403);

        $offboarding = EmployeeOffboardingRequest::create([
            'requested_by' => $actor->id,
            'employee_user_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'thai_name' => $employee->employee?->thai_name,
            'department' => $employee->employee?->department?->name ?? $employee->employee?->business_unit,
            'position' => $employee->employee?->position,
            'email' => $employee->email,
            'resignation_date' => $data['resignation_date'] ?? null,
            'hr_note' => $data['hr_note'] ?? null,
            'status' => 'pending_it',
        ]);

        collect(self::DEFAULT_SYSTEMS)->each(fn (string $system) => EmployeeOffboardingSystem::create([
            'employee_offboarding_request_id' => $offboarding->id,
            'system_name' => $system,
            'username' => $system === 'WDC Portal' ? $employee->employee_code : null,
            'email' => in_array($system, ['EMAIL', 'Active Directory'], true) ? $employee->email : null,
            'status' => 'pending',
        ]));

        $employee->itAssets->each(fn (ItAsset $asset) => EmployeeOffboardingSystem::create([
            'employee_offboarding_request_id' => $offboarding->id,
            'it_asset_id' => $asset->id,
            'system_name' => "คืนทรัพย์สิน: {$asset->code}",
            'notes' => $asset->name,
            'status' => 'pending',
        ]));

        $this->notifyUsers(['it.onboarding.manage', 'it.portal.view'], 'มีรายการพนักงานลาออกจาก HR', $offboarding->displayName(), route('offboarding.show', $offboarding));
        $this->log($request, 'create_employee_offboarding', EmployeeOffboardingRequest::class, $offboarding->id, "Created offboarding {$offboarding->employee_code}");

        return back()->with('status', 'ส่งรายการพนักงานลาออกให้ IT แล้ว');
    }

    public function claim(EmployeeOffboardingRequest $offboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageIt($actor), 403);
        abort_if(in_array($offboarding->status, ['it_completed', 'hr_approved'], true), 422);

        $updated = EmployeeOffboardingRequest::query()
            ->whereKey($offboarding->id)
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
            $owner = $offboarding->fresh('claimedBy')?->claimedBy?->name ?? 'ทีม IT คนอื่น';

            return back()->withErrors("รายการนี้มี {$owner} รับงานอยู่แล้ว");
        }

        $this->log($request, 'claim_employee_offboarding_it', EmployeeOffboardingRequest::class, $offboarding->id, "Claimed IT offboarding {$offboarding->employee_code}");

        return back()->with('status', 'รับงานปิดระบบแล้ว');
    }

    public function release(EmployeeOffboardingRequest $offboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageIt($actor), 403);
        abort_if(in_array($offboarding->status, ['it_completed', 'hr_approved'], true), 422);
        abort_unless($this->canWorkOnClaim($offboarding->fresh(), $actor), 403);

        $offboarding->update([
            'claimed_by_id' => null,
            'claimed_at' => null,
            'status' => 'pending_it',
        ]);

        $this->log($request, 'release_employee_offboarding_it', EmployeeOffboardingRequest::class, $offboarding->id, "Released IT offboarding {$offboarding->employee_code}");

        return back()->with('status', 'ปล่อยงานกลับเข้าคิว IT แล้ว');
    }

    public function updateIt(EmployeeOffboardingRequest $offboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageIt($actor), 403);
        abort_if($offboarding->status === 'hr_approved', 422);
        abort_unless($this->claimIfAvailable($offboarding, $actor), 403);

        $data = $request->validate([
            'systems' => ['nullable', 'array'],
            'systems.*.status' => ['nullable', Rule::in(EmployeeOffboardingSystem::STATUSES)],
            'systems.*.notes' => ['nullable', 'string', 'max:1000'],
            'it_note' => ['nullable', 'string', 'max:3000'],
        ]);

        foreach ($data['systems'] ?? [] as $systemId => $systemData) {
            $system = $offboarding->systems()->whereKey($systemId)->first();

            if (! $system) {
                continue;
            }

            $newStatus = $systemData['status'] ?? $system->status;
            $completedAt = $system->completed_at;
            $completedById = $system->completed_by_id;

            if ($newStatus === 'completed' && $system->status !== 'completed') {
                $completedAt = now();
                $completedById = $actor->id;
            }

            if ($newStatus === 'pending') {
                $completedAt = null;
                $completedById = null;
            }

            $system->update([
                'status' => $newStatus,
                'notes' => $systemData['notes'] ?? null,
                'completed_by_id' => $completedById,
                'completed_at' => $completedAt,
            ]);
        }

        $offboarding->update([
            'status' => 'in_progress',
            'it_note' => $data['it_note'] ?? $offboarding->it_note,
        ]);

        $this->log($request, 'update_employee_offboarding_it', EmployeeOffboardingRequest::class, $offboarding->id, "Updated offboarding IT checklist {$offboarding->employee_code}");

        return back()->with('status', 'บันทึก checklist ปิดระบบแล้ว');
    }

    public function completeIt(EmployeeOffboardingRequest $offboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($this->canManageIt($actor), 403);
        abort_if($offboarding->status === 'hr_approved', 422);
        abort_unless($this->claimIfAvailable($offboarding, $actor), 403);

        $offboarding->load('systems.asset');

        foreach ($offboarding->systems as $system) {
            if ($system->asset && $system->status === 'completed') {
                $system->asset->update([
                    'owner_id' => null,
                    'owner_name' => null,
                    'status' => 'stock',
                ]);

                EmployeeOnboardingAsset::query()
                    ->where('it_asset_id', $system->asset->id)
                    ->where('status', 'delivered')
                    ->update([
                        'status' => 'released',
                        'updated_at' => now(),
                    ]);
            }
        }

        $offboarding->update([
            'status' => 'it_completed',
            'it_completed_by' => $actor->id,
            'it_completed_at' => now(),
            'claimed_by_id' => $actor->id,
            'claimed_at' => $offboarding->claimed_at ?: now(),
        ]);

        $this->notifyUsers(['hr.employees.manage', 'hr.portal.view'], 'IT ปิดระบบพนักงานลาออกเรียบร้อยแล้ว', $offboarding->displayName(), route('offboarding.show', $offboarding));
        $this->log($request, 'complete_employee_offboarding_it', EmployeeOffboardingRequest::class, $offboarding->id, "Completed IT offboarding {$offboarding->employee_code}");

        return back()->with('status', 'แจ้ง HR ว่าปิดระบบเรียบร้อยแล้ว');
    }

    public function approve(EmployeeOffboardingRequest $offboarding, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides');

        abort_unless($actor->canAccess('hr.employees.manage'), 403);
        abort_unless($offboarding->status === 'it_completed', 422);

        $employee = $offboarding->employeeUser;

        if ($employee) {
            $employee->update(['is_active' => false]);

            $directoryData = [
                'user_id' => $employee->id,
                'entry_type' => 'employee',
                'employment_status' => 'resigned',
                'display_name' => $employee->name,
                'english_name' => $employee->employee?->english_name ?? $employee->name,
                'thai_name' => $employee->employee?->thai_name,
                'nickname' => $employee->employee?->nickname,
                'english_nickname' => $employee->employee?->english_nickname,
                'thai_nickname' => $employee->employee?->thai_nickname,
                'department' => $employee->employee?->department?->name ?? $employee->employee?->business_unit,
                'team' => $employee->employee?->team,
                'position' => $employee->employee?->position,
                'location' => $employee->employee?->location,
                'email' => $employee->email,
                'phone' => $employee->employee?->phone,
                'extension_number' => $employee->employee?->extension_number,
                'is_active' => false,
                'resigned_at' => $offboarding->resignation_date ?: now(),
                'imported_at' => now(),
            ];

            EmployeeDirectoryEntry::where(function ($query) use ($employee): void {
                $query->where('user_id', $employee->id)
                    ->orWhere(function ($subQuery) use ($employee): void {
                        $subQuery->where('source_system', 'wdc')
                            ->where('source_record_id', $employee->employee_code);
                    })
                    ->orWhere('email', $employee->email);
            })->update([
                'is_active' => false,
                'employment_status' => 'resigned',
                'resigned_at' => $offboarding->resignation_date ?: now(),
            ]);

            EmployeeDirectoryEntry::updateOrCreate(
                ['source_system' => 'wdc', 'source_record_id' => $employee->employee_code],
                [
                    ...$directoryData,
                    'is_active' => false,
                    'employment_status' => 'resigned',
                    'resigned_at' => $offboarding->resignation_date ?: now(),
                ],
            );
        }

        $offboarding->update([
            'status' => 'hr_approved',
            'hr_approved_by' => $actor->id,
            'hr_approved_at' => now(),
        ]);

        $this->log($request, 'approve_employee_offboarding', EmployeeOffboardingRequest::class, $offboarding->id, "Approved offboarding {$offboarding->employee_code}");

        return back()->with('status', 'ปิดบัญชีและย้ายพนักงานเป็นลาออกแล้ว');
    }

    private function claimIfAvailable(EmployeeOffboardingRequest $offboarding, User $actor): bool
    {
        $offboarding->refresh();

        if ($offboarding->claimed_by_id === $actor->id) {
            return true;
        }

        if ($offboarding->claimed_by_id) {
            return false;
        }

        return (bool) EmployeeOffboardingRequest::query()
            ->whereKey($offboarding->id)
            ->whereNull('claimed_by_id')
            ->update([
                'claimed_by_id' => $actor->id,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function canWorkOnClaim(EmployeeOffboardingRequest $offboarding, User $actor): bool
    {
        return $offboarding->claimed_by_id === null
            || $offboarding->claimed_by_id === $actor->id
            || $actor->canAccessAny(['admin.system.manage', 'admin.users.manage']);
    }

    private function notifyUsers(array $permissionKeys, string $title, string $body, string $url): void
    {
        $recipients = User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccessAny($permissionKeys));

        app(PortalNotificationService::class)->createForUsers($recipients, [
            'type' => 'offboarding',
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }

    private function canView(User $user): bool
    {
        return $user->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage', 'hr.employees.manage', 'hr.portal.view']);
    }

    private function canManageIt(User $user): bool
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
}
