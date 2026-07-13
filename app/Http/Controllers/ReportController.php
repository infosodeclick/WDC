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
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canOpenReports($user), 403);

        return view('reports.index', $this->reportPayload($user));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canOpenReports($user), 403);

        $rows = $this->overviewExportRows($this->reportPayload($user));

        return $request->string('format')->lower()->toString() === 'csv'
            ? $this->streamOverviewCsv($rows)
            : $this->streamOverviewExcel($rows);
    }

    public function print(Request $request): View
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canOpenReports($user), 403);

        return view('reports.print', [
            ...$this->reportPayload($user),
            'generatedAt' => now(),
        ]);
    }

    private function reportPayload(User $user): array
    {
        return [
            'summaryCards' => $this->summaryCards(),
            'ticketStatusRows' => $this->ticketStatusRows(),
            'employeeRows' => $this->employeeRows(),
            'assetRows' => $this->assetRows(),
            'licenseRows' => $this->licenseRows(),
            'onboardingRows' => $this->onboardingRows(),
            'offboardingRows' => $this->offboardingRows(),
            'sectionLinks' => $this->sectionLinks($user),
            'exportLinks' => $this->exportLinks($user),
        ];
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
            + Complaint::whereIn('status', Complaint::pendingStatuses())->count();

        return [
            ['label' => 'งาน IT ค้าง', 'value' => $helpdeskOpen, 'note' => 'IT Helpdesk', 'icon' => 'bi-life-preserver', 'target' => '#report-helpdesk'],
            ['label' => 'งานเกิน SLA', 'value' => $helpdeskOverdue, 'note' => 'เกินกำหนด', 'icon' => 'bi-stopwatch', 'target' => '#report-helpdesk'],
            ['label' => 'พนักงานใหม่รอดำเนินการ', 'value' => $pendingOnboarding, 'note' => 'HR / IT', 'icon' => 'bi-person-plus', 'target' => '#report-onboarding'],
            ['label' => 'พนักงานลาออกรอดำเนินการ', 'value' => $pendingOffboarding, 'note' => 'ปิดระบบ / คืนอุปกรณ์', 'icon' => 'bi-person-dash', 'target' => '#report-offboarding'],
            ['label' => 'ทรัพย์สินทั้งหมด', 'value' => ItAsset::count(), 'note' => 'INVENTORY', 'icon' => 'bi-box-seam', 'target' => '#report-assets'],
            ['label' => 'ใกล้หมดอายุ', 'value' => $warrantyExpiring + $licenseExpiring, 'note' => "ประกัน {$warrantyExpiring} / License {$licenseExpiring}", 'icon' => 'bi-shield-exclamation', 'target' => '#report-licenses'],
            ['label' => 'รายการรออนุมัติ', 'value' => $pendingApprovals, 'note' => 'HR / IT', 'icon' => 'bi-check2-square', 'target' => '#report-onboarding'],
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

    private function offboardingRows()
    {
        $labels = [
            'pending_it' => 'รอ IT ดำเนินการ',
            'in_progress' => 'IT กำลังดำเนินการ',
            'it_completed' => 'รอ HR ปิดบัญชี',
            'hr_approved' => 'เสร็จแล้ว',
            'cancelled' => 'ยกเลิก',
        ];

        return collect($labels)->map(fn (string $label, string $status): array => [
            'name' => $label,
            'count' => EmployeeOffboardingRequest::where('status', $status)->count(),
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

    private function sectionLinks(User $user): array
    {
        return [
            'helpdesk' => $user->canAccessAny(['tickets.manage', 'workflows.manage'])
                ? route('workflows.index', ['view' => 'tasks'])
                : null,
            'employees' => $user->canAccess('hr.employees.manage')
                ? route('hr.index', ['section' => 'employees'])
                : null,
            'assets' => $user->canExportItAssets()
                ? route('assets.index')
                : null,
            'onboarding' => $user->canAccess('hr.onboarding.manage')
                ? route('hr.index', ['section' => 'onboarding'])
                : ($user->canAccess('it.onboarding.manage') ? route('it.index', ['section' => 'onboarding']) : null),
            'offboarding' => $user->canAccess('hr.employees.manage')
                ? route('hr.index', ['section' => 'offboarding'])
                : ($user->canAccess('it.onboarding.manage') ? route('it.index', ['section' => 'offboarding']) : null),
        ];
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
            ['group' => 'ภาพรวม', 'label' => 'รายงานภาพรวม Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => route('reports.export', ['format' => 'xls']), 'visible' => true],
            ['group' => 'ภาพรวม', 'label' => 'รายงานภาพรวม CSV', 'icon' => 'bi-filetype-csv', 'url' => route('reports.export', ['format' => 'csv']), 'visible' => true],
            ['group' => 'ภาพรวม', 'label' => 'พิมพ์ / บันทึก PDF', 'icon' => 'bi-file-earmark-pdf', 'url' => route('reports.print'), 'visible' => true],
            ['group' => 'พนักงาน', 'label' => 'Export รายชื่อพนักงาน Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => route('hr.employees.export', ['format' => 'xls']), 'visible' => $user->canAccess('hr.employees.manage')],
            ['group' => 'พนักงาน', 'label' => 'Export รายชื่อพนักงาน CSV', 'icon' => 'bi-filetype-csv', 'url' => route('hr.employees.export', ['format' => 'csv']), 'visible' => $user->canAccess('hr.employees.manage')],
            ['group' => 'IT', 'label' => 'Export IT Checklist Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => route('it.onboarding.export', ['format' => 'xls']), 'visible' => $user->canAccess('it.onboarding.manage')],
            ['group' => 'IT', 'label' => 'Export IT Checklist CSV', 'icon' => 'bi-filetype-csv', 'url' => route('it.onboarding.export', ['format' => 'csv']), 'visible' => $user->canAccess('it.onboarding.manage')],
            ['group' => 'INVENTORY', 'label' => 'Export INVENTORY Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => route('assets.export', ['format' => 'xls']), 'visible' => $user->canExportItAssets()],
            ['group' => 'INVENTORY', 'label' => 'Export INVENTORY CSV', 'icon' => 'bi-filetype-csv', 'url' => route('assets.export', ['format' => 'csv']), 'visible' => $user->canExportItAssets()],
            ['group' => 'ศูนย์คำขอ', 'label' => 'Export ศูนย์คำขอ Excel', 'icon' => 'bi-file-earmark-spreadsheet', 'url' => route('workflows.export', ['format' => 'xls']), 'visible' => $user->canAccess('workflows.manage')],
            ['group' => 'ศูนย์คำขอ', 'label' => 'Export ศูนย์คำขอ CSV', 'icon' => 'bi-filetype-csv', 'url' => route('workflows.export', ['format' => 'csv']), 'visible' => $user->canAccess('workflows.manage')],
        ])->filter(fn (array $link): bool => $link['visible'])->values()->all();
    }

    private function overviewExportRows(array $payload): Collection
    {
        $rows = collect();
        $appendRows = function (string $group, iterable $items) use ($rows): void {
            foreach ($items as $item) {
                $rows->push([
                    'หมวด' => $group,
                    'รายการ' => is_array($item) ? $item['name'] : $item->name,
                    'จำนวน' => is_array($item) ? $item['count'] : $item->count,
                    'หมายเหตุ' => '',
                ]);
            }
        };

        foreach ($payload['summaryCards'] as $card) {
            $rows->push([
                'หมวด' => 'ภาพรวม',
                'รายการ' => $card['label'],
                'จำนวน' => $card['value'],
                'หมายเหตุ' => $card['note'],
            ]);
        }

        $appendRows('IT Helpdesk', $payload['ticketStatusRows']);
        $appendRows('พนักงานตามแผนก', $payload['employeeRows']);
        $appendRows('INVENTORY', $payload['assetRows']);
        $appendRows('Software License', $payload['licenseRows']);
        $appendRows('Onboarding', $payload['onboardingRows']);
        $appendRows('Offboarding', $payload['offboardingRows']);

        return $rows;
    }

    private function streamOverviewCsv(Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_keys($rows->first() ?? []));

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, 'wdc-overview-report-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function streamOverviewExcel(Collection $rows): StreamedResponse
    {
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
        }, 'wdc-overview-report-'.now()->format('Ymd-His').'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }
}
