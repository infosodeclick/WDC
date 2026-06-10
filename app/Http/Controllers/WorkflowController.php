<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Models\WorkflowRequestEvent;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $canManage = $this->canManageWorkflows($user);
        $status = $request->string('status')->toString();
        $templateId = $request->integer('template');

        $query = WorkflowRequest::with('template', 'requester.employee.department', 'currentStep', 'events.user')->latest();

        if (! $canManage) {
            $query->where('requester_id', $user->id);
        } elseif (! $user->canSeeAllData()) {
            if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
                $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
            } else {
                $query->where('requester_id', $user->id);
            }
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($templateId > 0) {
            $query->where('workflow_template_id', $templateId);
        }

        $templates = WorkflowTemplate::with('steps')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('workflows.index', [
            'templates' => $templates,
            'requests' => $query->paginate(12)->withQueryString(),
            'statusLabels' => WorkflowRequest::statusLabels(),
            'canManage' => $canManage,
            'canCreate' => $user->canAccess('workflows.create'),
            'activeStatus' => $status,
            'activeTemplateId' => $templateId,
            'metrics' => [
                'submitted' => (clone $this->scopedWorkflowQuery($user))->where('status', 'submitted')->count(),
                'in_review' => (clone $this->scopedWorkflowQuery($user))->where('status', 'in_review')->count(),
                'completed' => (clone $this->scopedWorkflowQuery($user))->whereIn('status', ['approved', 'completed'])->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('workflows.create'), 403);

        $data = $request->validate([
            'workflow_template_id' => ['required', 'exists:workflow_templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,critical'],
            'legacy_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $template = WorkflowTemplate::with('steps')->findOrFail($data['workflow_template_id']);
        $firstStep = $template->steps->first();

        $workflowRequest = WorkflowRequest::create([
            ...$data,
            'requester_id' => $request->user()->id,
            'current_step_id' => $firstStep?->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        WorkflowRequestEvent::create([
            'workflow_request_id' => $workflowRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'create',
            'to_status' => 'submitted',
            'comment' => "Created {$template->name} request",
        ]);

        $this->log($request, 'create_workflow_request', WorkflowRequest::class, $workflowRequest->id, "Created {$workflowRequest->title}");

        User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccess('workflows.manage'))
            ->each(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => 'workflow',
                'title' => 'มีคำขออนุมัติใหม่',
                'body' => "{$template->name}: {$workflowRequest->title}",
                'url' => route('workflows.index'),
            ]));

        return redirect()->route('workflows.index')->with('status', 'ส่งคำขอเข้าศูนย์อนุมัติเรียบร้อยแล้ว');
    }

    public function updateStatus(WorkflowRequest $workflowRequest, Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canManageWorkflows($user) && $this->canViewWorkflow($user, $workflowRequest->load('requester.employee.department')), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WorkflowRequest::statusLabels()))],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $fromStatus = $workflowRequest->status;
        $nextStep = $this->stepForStatus($workflowRequest, $data['status']);

        $workflowRequest->update([
            'status' => $data['status'],
            'current_step_id' => $nextStep?->id,
            'completed_at' => in_array($data['status'], ['approved', 'rejected', 'completed', 'cancelled'], true) ? now() : null,
        ]);

        WorkflowRequestEvent::create([
            'workflow_request_id' => $workflowRequest->id,
            'user_id' => $request->user()->id,
            'action' => 'status_change',
            'from_status' => $fromStatus,
            'to_status' => $data['status'],
            'comment' => $data['comment'] ?? null,
        ]);

        Notification::create([
            'user_id' => $workflowRequest->requester_id,
            'type' => 'workflow',
            'title' => 'สถานะคำขอเปลี่ยนแล้ว',
            'body' => "{$workflowRequest->title}: {$workflowRequest->statusLabel()}",
            'url' => route('workflows.index'),
        ]);

        $this->log($request, 'update_workflow_status', WorkflowRequest::class, $workflowRequest->id, "Changed workflow status to {$data['status']}");

        return back()->with('status', 'อัปเดตสถานะคำขอแล้ว');
    }

    private function stepForStatus(WorkflowRequest $workflowRequest, string $status): ?WorkflowStep
    {
        if (in_array($status, ['approved', 'rejected', 'completed', 'cancelled'], true)) {
            return null;
        }

        $steps = $workflowRequest->template->steps()->orderBy('step_order')->get();

        return match ($status) {
            'in_review' => $steps->skip(1)->first() ?? $steps->first(),
            default => $steps->first(),
        };
    }

    private function canManageWorkflows(User $user): bool
    {
        return $user->canAccess('workflows.manage');
    }

    private function canViewWorkflow(User $user, WorkflowRequest $workflowRequest): bool
    {
        if ($workflowRequest->requester_id === $user->id) {
            return true;
        }

        if (! $this->canManageWorkflows($user)) {
            return false;
        }

        if ($user->canSeeAllData()) {
            return true;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $workflowRequest->requester?->employee?->department_id === $user->employee->department_id;
        }

        return false;
    }

    private function scopedWorkflowQuery(User $user)
    {
        $query = WorkflowRequest::query();

        if (! $this->canManageWorkflows($user)) {
            return $query->where('requester_id', $user->id);
        }

        if ($user->canSeeAllData()) {
            return $query;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $query->whereHas('requester.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
        }

        return $query->where('requester_id', $user->id);
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
