<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\LegacySystem;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $query = Ticket::with('reporter.employee.department', 'assignee', 'comments.user')->latest();
        $canManage = $this->canManageTickets($user);

        if (! $canManage) {
            $query->where('reporter_id', $user->id);
        } elseif (! $user->canSeeAllData()) {
            if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
                $query->where('department_id', $user->employee->department_id);
            } else {
                $query->where('reporter_id', $user->id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('tickets.index', [
            'tickets' => $query->paginate(12)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'canManage' => $canManage,
            'canCreate' => $user->canAccess('tickets.create'),
            'status' => $request->string('status')->toString(),
            'requestTypes' => Ticket::requestTypeLabels(),
            'statusLabels' => Ticket::statusLabels(),
            'urgencyLabels' => Ticket::urgencyLabels(),
            'smartflowHelpdesk' => LegacySystem::where('key', 'smartflow-helpdesk')->first(),
        ]);
    }

    public function itDashboard(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($user->canAccess('it.portal.view') || $this->canManageTickets($user), 403);

        $ticketScope = $this->scopedTicketQuery($user);

        return view('it.index', [
            'newTickets' => (clone $ticketScope)->where('status', 'open')->count(),
            'pendingTickets' => (clone $ticketScope)->whereIn('status', ['accepted', 'in_progress'])->count(),
            'doneTickets' => (clone $ticketScope)->where('status', 'done')->count(),
            'tickets' => (clone $ticketScope)->with('reporter.employee.department', 'assignee')->latest()->paginate(12),
            'departments' => Department::orderBy('name')->get(),
            'requestTypes' => Ticket::requestTypeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('tickets.create'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'request_type' => ['required', Rule::in(array_keys(Ticket::requestTypeLabels()))],
            'details' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:low,normal,high,critical'],
            'legacy_document_ref' => ['nullable', 'string', 'max:80'],
        ]);

        $ticket = Ticket::create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'department_id' => $request->user()->employee?->department_id,
            'status' => 'open',
        ]);

        $this->log($request, 'create_ticket', Ticket::class, $ticket->id, "Created ticket {$ticket->title}");

        User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccess('tickets.manage'))
            ->each(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => 'ticket',
                'title' => 'Ticket ใหม่',
                'body' => $ticket->title,
                'url' => route('tickets.index'),
            ]));

        return redirect()->route('tickets.index')->with('status', 'เปิด Ticket เรียบร้อยแล้ว');
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
