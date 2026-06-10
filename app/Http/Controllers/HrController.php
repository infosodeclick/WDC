<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccessAny(['hr.portal.view', 'hr.employees.manage', 'hr.announcements.manage', 'complaints.review']), 403);

        $employees = User::with('role', 'employee.department')->orderBy('employee_code');

        if (! $actor->canSeeAllData()) {
            if ($actor->canSeeDepartmentData() && $actor->employee?->department_id) {
                $employees->whereHas('employee', fn ($query) => $query->where('department_id', $actor->employee->department_id));
            } else {
                $employees->where('id', $actor->id);
            }
        }

        $complaints = Complaint::with('reporter')->latest()->take(8);

        if (! $actor->canAccess('complaints.review')) {
            $complaints->whereRaw('1 = 0');
        }

        return view('hr.index', [
            'employees' => $employees->get(),
            'departments' => Department::orderBy('name')->get(),
            'announcements' => Announcement::with('department')->latest()->take(8)->get(),
            'complaints' => $complaints->get(),
            'canManageAnnouncements' => $actor->canAccess('hr.announcements.manage'),
            'canManageEmployees' => $actor->canAccess('hr.employees.manage'),
            'canReviewComplaints' => $actor->canAccess('complaints.review'),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('hr.announcements.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:5000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_urgent' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $announcement = Announcement::create([
            ...$data,
            'created_by' => $request->user()->id,
            'is_pinned' => $request->boolean('is_pinned'),
            'is_urgent' => $request->boolean('is_urgent'),
            'published_at' => now(),
        ]);

        User::where('is_active', true)->get()->each(fn (User $user) => Notification::create([
            'user_id' => $user->id,
            'type' => 'announcement',
            'title' => 'ประกาศใหม่',
            'body' => $announcement->title,
            'url' => route('announcements.index'),
        ]));

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_announcement',
            'subject_type' => Announcement::class,
            'subject_id' => $announcement->id,
            'description' => "Created announcement {$announcement->title}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'สร้างประกาศเรียบร้อยแล้ว');
    }

    public function updateEmployeeStatus(User $user, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccess('hr.employees.manage'), 403);
        abort_if($actor->id === $user->id, 422, 'Cannot suspend your own account.');
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if (! $actor->canSeeAllData()) {
            abort_unless($actor->canSeeDepartmentData() && $actor->employee?->department_id === $user->employee?->department_id, 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_employee_status',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'description' => "Changed {$user->employee_code} status",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'อัปเดตสถานะพนักงานแล้ว');
    }
}
