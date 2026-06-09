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
        $user = $request->user()->load('role', 'employee.department');
        $canManage = $this->canManageWorkflows($user);
        $status = $request->string('status')->toString();
        $templateId = $request->integer('template');

        $query = WorkflowRequest::with('template', 'requester.employee.department', 'currentStep', 'events.user')->latest();

        if (! $canManage) {
            $query->where('requester_id', $user->id);
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
            'activeStatus' => $status,
            'activeTemplateId' => $templateId,
            'metrics' => [
                'submitted' => WorkflowRequest::where('status', 'submitted')->count(),
                'in_review' => WorkflowRequest::where('status', 'in_review')->count(),
                'completed' => WorkflowRequest::whereIn('status', ['approved', 'completed'])->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        User::whereHas('role', fn ($query) => $query->whereIn('slug', ['supervisor', 'hr', 'admin']))
            ->orWhereHas('employee.department', fn ($query) => $query->where('code', 'IT'))
            ->get()
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
        abort_unless($this->canManageWorkflows($request->user()->load('role', 'employee.department')), 403);

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
        return $user->hasAnyRole(['supervisor', 'hr', 'admin']) || $user->isInDepartment('IT');
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
