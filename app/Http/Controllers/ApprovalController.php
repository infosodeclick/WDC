<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canOpenApprovalCenter($user), 403);

        $sections = collect([
            $this->itQueue($user),
            $this->hrQueue($user),
            $this->workflowQueue($user),
        ])
            ->filter(fn (array $section): bool => $section['visible'])
            ->map(fn (array $section): array => [
                ...$section,
                'items' => $section['items']->sortByDesc('updated_at')->values(),
            ])
            ->values();

        return view('approvals.index', [
            'sections' => $sections,
            'totalPending' => $sections->sum(fn (array $section): int => $section['items']->count()),
        ]);
    }

    private function canOpenApprovalCenter(User $user): bool
    {
        return $user->canAccessAny([
            'workflows.manage',
            'hr.onboarding.manage',
            'hr.employees.manage',
            'complaints.review',
            'it.onboarding.manage',
            'tickets.manage',
            'admin.users.manage',
            'admin.roles.manage',
            'audit.logs.view',
        ]);
    }

    private function itQueue(User $user): array
    {
        $visible = $user->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage']);
        $items = collect();

        if ($visible) {
            $items = $items
                ->merge(EmployeeOnboardingRequest::with('department', 'claimedBy')
                    ->whereIn('status', ['pending_it', 'in_progress', 'cancel_requested'])
                    ->latest()
                    ->take(20)
                    ->get()
                    ->map(fn (EmployeeOnboardingRequest $request): array => [
                        'type' => 'พนักงานใหม่',
                        'title' => $request->displayName(),
                        'code' => $request->employee_code,
                        'status' => $request->statusLabel(),
                        'owner' => $request->claimedBy?->name ?: 'ยังไม่มีผู้รับงาน',
                        'meta' => collect([$request->department?->name ?? $request->business_unit, $request->position, $request->start_date?->format('d/m/Y')])->filter()->join(' · '),
                        'url' => route('onboarding.show', $request),
                        'updated_at' => $request->updated_at,
                    ]))
                ->merge(EmployeeOffboardingRequest::with('claimedBy')
                    ->whereIn('status', ['pending_it', 'in_progress'])
                    ->latest()
                    ->take(20)
                    ->get()
                    ->map(fn (EmployeeOffboardingRequest $request): array => [
                        'type' => 'พนักงานลาออก',
                        'title' => $request->employee_name,
                        'code' => $request->employee_code,
                        'status' => $request->statusLabel(),
                        'owner' => $request->claimedBy?->name ?: 'ยังไม่มีผู้รับงาน',
                        'meta' => collect([$request->department, $request->position, $request->resignation_date?->format('d/m/Y')])->filter()->join(' · '),
                        'url' => route('offboarding.show', $request),
                        'updated_at' => $request->updated_at,
                    ]));
        }

        return [
            'key' => 'it',
            'title' => 'IT',
            'subtitle' => 'รายการที่รอทีม IT รับงาน เปิด/ปิดระบบ หรือยืนยันการยกเลิก',
            'icon' => 'bi-tools',
            'visible' => $visible,
            'items' => $items,
        ];
    }

    private function hrQueue(User $user): array
    {
        $visible = $user->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage', 'complaints.review']);
        $items = collect();

        if ($visible) {
            if ($user->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage'])) {
                $items = $items
                    ->merge(EmployeeOnboardingRequest::with('department', 'itCompleter')
                        ->where('status', 'it_completed')
                        ->latest()
                        ->take(20)
                        ->get()
                        ->map(fn (EmployeeOnboardingRequest $request): array => [
                            'type' => 'อนุมัติแสดงรายชื่อ',
                            'title' => $request->displayName(),
                            'code' => $request->employee_code,
                            'status' => $request->statusLabel(),
                            'owner' => $request->itCompleter?->name ?: 'IT ส่งกลับแล้ว',
                            'meta' => collect([$request->department?->name ?? $request->business_unit, $request->position, $request->it_completed_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                            'url' => route('onboarding.show', $request),
                            'updated_at' => $request->updated_at,
                        ]))
                    ->merge(EmployeeOffboardingRequest::with('itCompleter')
                        ->where('status', 'it_completed')
                        ->latest()
                        ->take(20)
                        ->get()
                        ->map(fn (EmployeeOffboardingRequest $request): array => [
                            'type' => 'อนุมัติปิดบัญชี',
                            'title' => $request->employee_name,
                            'code' => $request->employee_code,
                            'status' => $request->statusLabel(),
                            'owner' => $request->itCompleter?->name ?: 'IT ส่งกลับแล้ว',
                            'meta' => collect([$request->department, $request->position, $request->it_completed_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                            'url' => route('offboarding.show', $request),
                            'updated_at' => $request->updated_at,
                        ]))
                    ->merge(ProfileChangeRequest::with('user.employee.department')
                        ->where('status', 'pending')
                        ->latest()
                        ->take(20)
                        ->get()
                        ->map(fn (ProfileChangeRequest $request): array => [
                            'type' => 'แก้ข้อมูลโปรไฟล์',
                            'title' => $request->user?->name ?? 'ไม่ระบุผู้ใช้',
                            'code' => $request->user?->employee_code,
                            'status' => 'รอ HR ตรวจสอบ',
                            'owner' => $request->field,
                            'meta' => collect([$request->current_value, '→', $request->requested_value])->filter(fn ($value) => $value !== null && $value !== '')->join(' '),
                            'url' => route('hr.index', ['section' => 'profile-requests']),
                            'updated_at' => $request->updated_at,
                        ]));
            }

            if ($user->canAccess('complaints.review')) {
                $items = $items->merge(Complaint::with('reporter')
                    ->whereIn('status', ['submitted', 'in_review', 'pending'])
                    ->latest()
                    ->take(20)
                    ->get()
                    ->map(fn (Complaint $complaint): array => [
                        'type' => 'เรื่องร้องเรียน',
                        'title' => $complaint->subject,
                        'code' => $complaint->is_anonymous ? 'ไม่ระบุชื่อ' : $complaint->reporter?->employee_code,
                        'status' => $complaint->status,
                        'owner' => 'HR',
                        'meta' => $complaint->created_at?->format('d/m/Y H:i'),
                        'url' => route('complaints.index'),
                        'updated_at' => $complaint->updated_at,
                    ]));
            }
        }

        return [
            'key' => 'hr',
            'title' => 'HR',
            'subtitle' => 'รายการที่รอ HR อนุมัติ ตรวจสอบ หรือปิดงานกลับเข้าระบบ',
            'icon' => 'bi-people',
            'visible' => $visible,
            'items' => $items,
        ];
    }

    private function workflowQueue(User $user): array
    {
        $visible = $user->canAccess('workflows.manage');
        $items = collect();

        if ($visible) {
            $items = WorkflowRequest::with('template', 'requester', 'assignee')
                ->whereIn('status', ['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester'])
                ->latest()
                ->take(30)
                ->get()
                ->map(fn (WorkflowRequest $request): array => [
                    'type' => $request->template?->name ?: 'Workflow',
                    'title' => $request->title,
                    'code' => $request->document_number ?: $request->legacy_reference,
                    'status' => $request->statusLabel(),
                    'owner' => $request->assignee?->name ?: ($request->assigned_group ?: 'ยังไม่ระบุผู้รับผิดชอบ'),
                    'meta' => collect([$request->requester?->name, $request->due_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                    'url' => route('workflows.index'),
                    'updated_at' => $request->updated_at,
                ]);
        }

        return [
            'key' => 'workflow',
            'title' => 'คำขอ/อนุมัติ',
            'subtitle' => 'คำขอจาก Workflow ที่ยังไม่ปิดงานหรือรอผู้เกี่ยวข้องดำเนินการ',
            'icon' => 'bi-kanban',
            'visible' => $visible,
            'items' => $items,
        ];
    }
}
