<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\ItAsset;
use App\Models\ProfileChangeRequest;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Services\ItHelpdeskWorkflow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canOpenReports($user), 403);

        return view('reports.index', [
            'summaryCards' => $this->summaryCards(),
            'ticketStatusRows' => $this->ticketStatusRows(),
            'employeeRows' => $this->employeeRows(),
            'assetRows' => $this->assetRows(),
            'licenseRows' => $this->licenseRows(),
            'onboardingRows' => $this->onboardingRows(),
            'actionRows' => $this->actionRows(),
            'exportLinks' => $this->exportLinks($user),
        ]);
    }

    private function canOpenReports(User $user): bool
    {
        return $user->canAccessAny([
            'tickets.manage',
            'hr.employees.manage',
            'hr.onboarding.manage',
            'complaints.review',
            'assets.reports',
            'audit.logs.view',
            'admin.activity.view',
            'admin.system.manage',
            'workflows.manage',
        ]);
    }

    private function summaryCards(): array
    {
        $helpdeskOpen = $this->helpdeskQuery()
            ->whereNotIn('status', ItHelpdeskWorkflow::TERMINAL_STATUSES)
            ->count();
        $helpdeskOverdue = $this->helpdeskQuery()
            ->whereNotIn('status', ItHelpdeskWorkflow::TERMINAL_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();
        $pendingOnboarding = EmployeeOnboardingRequest::whereIn('status', ['pending_it', 'in_progress', 'it_completed', 'cancel_requested'])->count();
        $pendingOffboarding = EmployeeOffboardingRequest::whereIn('status', ['pending_it', 'in_progress', 'it_completed'])->count();
        $warrantyExpiring = ItAsset::whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [now()->toDateString(), now()->addDays(90)->toDateString()])
            ->count();
        $licenseExpiring = SoftwareLicense::whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(90)->toDateString()])
            ->count();
        $pendingApprovals = $pendingOnboarding
            + $pendingOffboarding
            + ProfileChangeRequest::where('status', 'pending')->count()
            + Complaint::whereIn('status', ['submitted', 'in_review', 'pending'])->count();

        return [
            ['label' => 'งาน IT ค้าง', 'value' => $helpdeskOpen, 'note' => 'จาก IT Helpdesk Workflow', 'icon' => 'bi-life-preserver'],
            ['label' => 'งานเกิน SLA', 'value' => $helpdeskOverdue, 'note' => 'เกินกำหนดใน Workflow', 'icon' => 'bi-stopwatch'],
            ['label' => 'พนักงานใหม่รอดำเนินการ', 'value' => $pendingOnboarding, 'note' => 'HR/IT ยังไม่ครบขั้นตอน', 'icon' => 'bi-person-plus'],
            ['label' => 'พนักงานลาออกรอดำเนินการ', 'value' => $pendingOffboarding, 'note' => 'รอปิดระบบ/รับคืนอุปกรณ์', 'icon' => 'bi-person-dash'],
            ['label' => 'ทรัพย์สินทั้งหมด', 'value' => ItAsset::count(), 'note' => 'ในทะเบียน INVENTORY', 'icon' => 'bi-box-seam'],
            ['label' => 'ใกล้หมดประกัน', 'value' => $warrantyExpiring, 'note' => 'ภายใน 90 วัน', 'icon' => 'bi-shield-exclamation'],
            ['label' => 'License ใกล้หมดอายุ', 'value' => $licenseExpiring, 'note' => 'ภายใน 90 วัน', 'icon' => 'bi-key'],
            ['label' => 'รายการรออนุมัติ', 'value' => $pendingApprovals, 'note' => 'จาก HR, IT และเรื่องร้องเรียน', 'icon' => 'bi-check2-square'],
            ['label' => 'พนักงานใช้งานอยู่', 'value' => EmployeeDirectoryEntry::where('is_active', true)->count(), 'note' => 'แสดงในรายชื่อพนักงาน', 'icon' => 'bi-people'],
        ];
    }

    private function ticketStatusRows()
    {
        $labels = collect(WorkflowRequest::statusLabels())
            ->only(['submitted', 'in_review', 'accepted', 'in_progress', 'waiting_requester', 'approved', 'completed', 'rejected', 'cancelled']);

        return collect($labels)->map(fn (string $label, string $status): array => [
            'name' => $label,
            'count' => $this->helpdeskQuery()->where('status', $status)->count(),
        ])->values();
    }

    private function employeeRows()
    {
        return EmployeeDirectoryEntry::selectRaw("COALESCE(NULLIF(department, ''), 'ไม่ระบุแผนก') as name, COUNT(*) as count")
            ->where('is_active', true)
            ->groupBy('name')
            ->orderByDesc('count')
            ->take(8)
            ->get();
    }

    private function assetRows()
    {
        $labels = [
            'active' => 'ใช้งานอยู่',
            'repair' => 'ส่งซ่อม',
            'lost' => 'สูญหาย',
            'retired' => 'จำหน่าย/เลิกใช้',
        ];

        return collect($labels)->map(fn (string $label, string $status): array => [
            'name' => $label,
            'count' => ItAsset::where('status', $status)->count(),
        ])->values();
    }

    private function onboardingRows()
    {
        $onboardingLabels = [
            'pending_it' => 'รอ IT เปิดระบบ',
            'in_progress' => 'IT กำลังดำเนินการ',
            'it_completed' => 'รอ HR อนุมัติ',
            'hr_approved' => 'เสร็จแล้ว',
            'cancel_requested' => 'รอตรวจสอบยกเลิก',
            'cancelled' => 'ยกเลิก',
        ];

        return collect($onboardingLabels)->map(fn (string $label, string $status): array => [
            'name' => $label,
            'count' => EmployeeOnboardingRequest::where('status', $status)->count(),
        ])->values();
    }

    private function licenseRows()
    {
        $labels = [
            'active' => 'ใช้งานอยู่',
            'expiring' => 'ใกล้หมดอายุ',
            'expired' => 'หมดอายุ',
            'cancelled' => 'ยกเลิก',
        ];

        return collect($labels)->map(fn (string $label, string $status): array => [
            'name' => $label,
            'count' => SoftwareLicense::where('status', $status)->count(),
        ])->values();
    }

    private function actionRows()
    {
        return collect([
            [
                'label' => 'งาน IT ค้างล่าสุด',
                'items' => $this->helpdeskQuery()
                    ->with('requester')
                    ->whereNotIn('status', ItHelpdeskWorkflow::TERMINAL_STATUSES)
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn (WorkflowRequest $workflow): array => [
                        'title' => $workflow->title,
                        'meta' => collect([$workflow->requester?->name, $workflow->statusLabel(), $workflow->created_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                        'url' => route('workflows.show', $workflow),
                    ]),
            ],
            [
                'label' => 'Onboarding ล่าสุด',
                'items' => EmployeeOnboardingRequest::latest()
                    ->take(5)
                    ->get()
                    ->map(fn (EmployeeOnboardingRequest $onboarding): array => [
                        'title' => $onboarding->displayName(),
                        'meta' => collect([$onboarding->employee_code, $onboarding->statusLabel(), $onboarding->start_date?->format('d/m/Y')])->filter()->join(' · '),
                        'url' => route('onboarding.show', $onboarding),
                    ]),
            ],
            [
                'label' => 'คำขออื่นค้างล่าสุด',
                'items' => WorkflowRequest::with('template')
                    ->whereDoesntHave('template', fn ($query) => $query
                        ->where('source_system', 'smartflow')
                        ->whereIn('legacy_workflow_id', ItHelpdeskWorkflow::IT_LEGACY_IDS))
                    ->whereNotIn('status', ItHelpdeskWorkflow::TERMINAL_STATUSES)
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn (WorkflowRequest $workflow): array => [
                        'title' => $workflow->title,
                        'meta' => collect([$workflow->template?->name, $workflow->statusLabel(), $workflow->created_at?->format('d/m/Y H:i')])->filter()->join(' · '),
                        'url' => route('workflows.index'),
                    ]),
            ],
        ]);
    }

    private function helpdeskQuery()
    {
        return WorkflowRequest::query()
            ->whereHas('template', fn ($query) => $query
                ->where('source_system', 'smartflow')
                ->whereIn('legacy_workflow_id', ItHelpdeskWorkflow::IT_LEGACY_IDS));
    }

    private function exportLinks(User $user): array
    {
        return collect([
            ['label' => 'Export รายชื่อพนักงาน CSV', 'url' => route('hr.employees.export', ['format' => 'csv']), 'visible' => $user->canAccess('hr.employees.manage')],
            ['label' => 'Export รายชื่อพนักงาน Excel', 'url' => route('hr.employees.export', ['format' => 'xlsx']), 'visible' => $user->canAccess('hr.employees.manage')],
            ['label' => 'Export IT Checklist CSV', 'url' => route('it.onboarding.export', ['format' => 'csv']), 'visible' => $user->canAccess('it.onboarding.manage')],
            ['label' => 'Export INVENTORY CSV', 'url' => route('assets.export'), 'visible' => $user->canExportItAssets()],
            ['label' => 'Export Workflow CSV', 'url' => route('workflows.export'), 'visible' => $user->canAccess('workflows.manage')],
        ])->filter(fn (array $link): bool => $link['visible'])->values()->all();
    }
}
