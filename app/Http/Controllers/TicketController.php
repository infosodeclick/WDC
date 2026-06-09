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
        $user = $request->user()->load('role', 'employee.department');
        $query = Ticket::with('reporter.employee.department', 'assignee', 'comments.user')->latest();

        if (! $this->canManageTickets($user)) {
            $query->where('reporter_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('tickets.index', [
            'tickets' => $query->paginate(12)->withQueryString(),
            'departments' => Department::orderBy('name')->get(),
            'canManage' => $this->canManageTickets($user),
            'status' => $request->string('status')->toString(),
            'requestTypes' => Ticket::requestTypeLabels(),
            'statusLabels' => Ticket::statusLabels(),
            'urgencyLabels' => Ticket::urgencyLabels(),
            'smartflowHelpdesk' => LegacySystem::where('key', 'smartflow-helpdesk')->first(),
        ]);
    }

    public function itDashboard(Request $request): View
    {
        abort_unless($this->canManageTickets($request->user()->load('role', 'employee.department')), 403);

        return view('it.index', [
            'newTickets' => Ticket::where('status', 'open')->count(),
            'pendingTickets' => Ticket::whereIn('status', ['accepted', 'in_progress'])->count(),
            'doneTickets' => Ticket::where('status', 'done')->count(),
            'tickets' => Ticket::with('reporter.employee.department', 'assignee')->latest()->paginate(12),
            'departments' => Department::orderBy('name')->get(),
            'requestTypes' => Ticket::requestTypeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        User::whereHas('employee.department', fn ($query) => $query->where('code', 'IT'))
            ->orWhereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->get()
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
        abort_unless($this->canViewTicket($request->user()->load('role', 'employee.department'), $ticket), 403);

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
        abort_unless($this->canManageTickets($request->user()->load('role', 'employee.department')), 403);

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
        return $user->hasRole('admin') || $user->isInDepartment('IT');
    }

    private function canViewTicket(User $user, Ticket $ticket): bool
    {
        return $ticket->reporter_id === $user->id || $this->canManageTickets($user);
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
