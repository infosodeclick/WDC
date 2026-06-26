<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\ItAsset;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\WorkflowRequest;
use App\Services\ItHelpdeskWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request, ItHelpdeskWorkflow $helpdesk): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($user->canAccessAny(['tickets.create', 'tickets.manage', 'workflows.create', 'workflows.manage']), 403);

        return redirect()->to($helpdesk->route([
            'status' => $this->workflowStatusFromTicketStatus($request->string('status')->toString()),
        ]));
    }

    public function itDashboard(Request $request, ItHelpdeskWorkflow $helpdesk): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($user->canAccessAny(['it.portal.view', 'tickets.manage', 'workflows.manage']), 403);

        $workflowScope = $helpdesk->queryFor($user, $this->canManageHelpdesk($user));

        return view('it.index', [
            'newTickets' => (clone $workflowScope)->where('status', 'submitted')->count(),
            'pendingTickets' => (clone $workflowScope)->whereIn('status', ['in_review', 'accepted', 'in_progress', 'waiting_requester'])->count(),
            'doneTickets' => (clone $workflowScope)->whereIn('status', ItHelpdeskWorkflow::DONE_STATUSES)->count(),
            'requests' => (clone $workflowScope)->paginate(12),
            'onboardingRequests' => $user->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage'])
                ? EmployeeOnboardingRequest::with('department', 'systems.asset', 'systems.provisioner', 'requester', 'claimedBy')
                    ->whereIn('status', ['pending_it', 'in_progress', 'it_completed'])
                    ->latest()
                    ->take(12)
                    ->get()
                : collect(),
            'offboardingRequests' => $user->canAccessAny(['it.onboarding.manage', 'it.portal.view', 'tickets.manage'])
                ? EmployeeOffboardingRequest::with('systems.asset', 'systems.completer', 'requester', 'claimedBy', 'employeeUser.employee.department')
                    ->whereIn('status', ['pending_it', 'in_progress', 'it_completed'])
                    ->latest()
                    ->take(12)
                    ->get()
                : collect(),
            'availableAssets' => $user->canManageItAssets()
                ? ItAsset::whereIn('status', ['active', 'repair'])->orderBy('code')->get()
                : collect(),
            'itHelpdeskUrl' => $helpdesk->route(),
        ]);
    }

    public function store(Request $request, ItHelpdeskWorkflow $helpdesk): RedirectResponse
    {
        abort_unless($request->user()->canAccessAny(['tickets.create', 'workflows.create']), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'request_type' => ['required', Rule::in(array_keys(Ticket::requestTypeLabels()))],
            'details' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:low,normal,high,critical'],
            'legacy_document_ref' => ['nullable', 'string', 'max:80'],
        ]);

        $workflowRequest = $helpdesk->createFromLegacyTicketInput($request->user(), $data);

        $this->log($request, 'create_helpdesk_workflow_request', WorkflowRequest::class, $workflowRequest->id, "Created helpdesk workflow {$workflowRequest->title}");

        User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccess('workflows.manage'))
            ->each(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => 'workflow',
                'title' => 'มีคำขอ IT Helpdesk ใหม่',
                'body' => $workflowRequest->title,
                'url' => $helpdesk->route(),
            ]));

        return redirect()->to($helpdesk->route())->with('status', 'ส่งคำขอ IT Helpdesk เข้าศูนย์ Workflow แล้ว');
    }

    public function comment(Ticket $ticket, Request $request): RedirectResponse
    {
        abort_unless($this->canViewTicket($request->user()->load('role.permissions', 'permissionOverrides', 'employee.department'), $ticket), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => false,
        ]);

        $this->log($request, 'comment_ticket', Ticket::class, $ticket->id, "Commented on ticket {$ticket->title}");

        if ($ticket->reporter_id !== $request->user()->id) {
            Notification::create([
                'user_id' => $ticket->reporter_id,
                'type' => 'ticket',
                'title' => 'Ticket ถูกตอบกลับ',
                'body' => $ticket->title,
                'url' => route('tickets.index'),
            ]);
        }

        return back()->with('status', 'เพิ่มความคิดเห็นแล้ว');
    }

    public function updateStatus(Ticket $ticket, Request $request): RedirectResponse
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($this->canManageTickets($user) && $this->canViewTicket($user, $ticket), 403);

        $data = $request->validate(['status' => ['required', 'in:open,accepted,in_progress,done']]);
        $ticket->update([
            'status' => $data['status'],
            'assigned_to' => $ticket->assigned_to ?: $request->user()->id,
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ]);

        $this->log($request, 'update_ticket_status', Ticket::class, $ticket->id, "Changed ticket status to {$data['status']}");

        Notification::create([
            'user_id' => $ticket->reporter_id,
            'type' => 'ticket',
            'title' => 'สถานะ Ticket เปลี่ยนแล้ว',
            'body' => "{$ticket->title}: {$data['status']}",
            'url' => route('tickets.index'),
        ]);

        return back()->with('status', 'อัปเดตสถานะ Ticket แล้ว');
    }

    private function canManageTickets(User $user): bool
    {
        return $user->canAccess('tickets.manage');
    }

    private function canManageHelpdesk(User $user): bool
    {
        return $user->canAccessAny(['tickets.manage', 'workflows.manage', 'it.portal.view']);
    }

    private function workflowStatusFromTicketStatus(string $status): ?string
    {
        return match ($status) {
            'open' => 'submitted',
            'accepted' => 'accepted',
            'in_progress' => 'in_progress',
            'done' => 'completed',
            default => null,
        };
    }

    private function canViewTicket(User $user, Ticket $ticket): bool
    {
        if ($ticket->reporter_id === $user->id) {
            return true;
        }

        if (! $this->canManageTickets($user)) {
            return false;
        }

        if ($user->canSeeAllData()) {
            return true;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $ticket->department_id === $user->employee->department_id;
        }

        return false;
    }

    private function scopedTicketQuery(User $user)
    {
        $query = Ticket::query();

        if (! $this->canManageTickets($user)) {
            return $query->where('reporter_id', $user->id);
        }

        if ($user->canSeeAllData()) {
            return $query;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $query->where('department_id', $user->employee->department_id);
        }

        return $query->where('reporter_id', $user->id);
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
