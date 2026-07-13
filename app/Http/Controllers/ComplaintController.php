<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $query = Complaint::with('reporter.employee.department')->latest();
        $canReview = $user->canAccess('complaints.review');

        if (! $canReview) {
            $query->where('reporter_id', $user->id);
        } elseif (! $user->canSeeAllData()) {
            if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
                $query->where(function ($query) use ($user) {
                    $query->whereNull('reporter_id')
                        ->orWhereHas('reporter.employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
                });
            } else {
                $query->where('reporter_id', $user->id);
            }
        }

        return view('complaints.index', [
            'complaints' => $query->paginate(12),
            'canReview' => $canReview,
            'canCreate' => $user->canAccess('complaints.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('complaints.create'), 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
        ]);

        $complaint = Complaint::create([
            ...$data,
            'type' => 'ร้องเรียน',
            'submitted_to' => 'hr',
            'is_anonymous' => true,
            'reporter_id' => null,
            'status' => 'submitted',
        ]);

        ActivityLog::create([
            'user_id' => null,
            'action' => 'create_complaint',
            'subject_type' => Complaint::class,
            'subject_id' => $complaint->id,
            'description' => 'Submitted anonymous complaint to HR',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $reviewers = User::with('role.permissions', 'permissionOverrides')
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->canAccess('complaints.review'));

        app(PortalNotificationService::class)->createForUsers($reviewers, [
            'type' => 'complaint',
            'title' => 'มีเรื่องร้องเรียนใหม่',
            'body' => $complaint->subject,
            'url' => route('complaints.index'),
        ]);

        return redirect()->route('complaints.index')->with('status', 'ส่งเรื่องเรียบร้อยแล้ว');
    }

    public function updateStatus(Complaint $complaint, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccess('complaints.review'), 403);
        abort_unless($this->canReviewComplaint($actor, $complaint->load('reporter.employee.department')), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::statusLabels()))],
        ]);
        $complaint->update([
            'status' => $data['status'],
            'assigned_to' => $complaint->assigned_to ?: $request->user()->id,
            'reviewed_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : $complaint->reviewed_at,
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_complaint_status',
            'subject_type' => Complaint::class,
            'subject_id' => $complaint->id,
            'description' => "Changed complaint status to {$data['status']}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'อัปเดตสถานะเรื่องร้องเรียนแล้ว');
    }

    private function canReviewComplaint(User $user, Complaint $complaint): bool
    {
        if ($user->canSeeAllData()) {
            return true;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $complaint->reporter_id === null
                || $complaint->reporter?->employee?->department_id === $user->employee->department_id;
        }

        return $complaint->reporter_id === $user->id;
    }
}
