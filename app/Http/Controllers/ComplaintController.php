<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('role');
        $query = Complaint::with('reporter.employee.department')->latest();

        if (! $user->hasAnyRole(['hr', 'admin'])) {
            $query->where('reporter_id', $user->id);
        }

        return view('complaints.index', [
            'complaints' => $query->paginate(12),
            'canReview' => $user->hasAnyRole(['hr', 'admin']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:เสนอแนะ,ร้องเรียน,แจ้งการทุจริต,แจ้งปัญหาหัวหน้างาน'],
            'subject' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'submitted_to' => ['required', 'in:hr,executive'],
        ]);

        $isAnonymous = $request->boolean('is_anonymous');
        $complaint = Complaint::create([
            ...$data,
            'is_anonymous' => $isAnonymous,
            'reporter_id' => $isAnonymous ? null : $request->user()->id,
            'status' => 'submitted',
        ]);

        ActivityLog::create([
            'user_id' => $isAnonymous ? null : $request->user()->id,
            'action' => 'create_complaint',
            'subject_type' => Complaint::class,
            'subject_id' => $complaint->id,
            'description' => 'Submitted complaint or suggestion',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        User::whereHas('role', fn ($query) => $query->whereIn('slug', ['hr', 'admin']))->get()
            ->each(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => 'complaint',
                'title' => 'มีเรื่องร้องเรียน/เสนอแนะใหม่',
                'body' => $complaint->subject,
                'url' => route('complaints.index'),
            ]));

        return redirect()->route('complaints.index')->with('status', 'ส่งเรื่องเรียบร้อยแล้ว');
    }

    public function updateStatus(Complaint $complaint, Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['hr', 'admin']), 403);

        $data = $request->validate(['status' => ['required', 'in:submitted,reviewing,resolved,closed']]);
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
}
