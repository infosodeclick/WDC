<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOnboardingRequest;
use App\Models\Notification;
use App\Models\ProfileChangeRequest;
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

        $canManageAnnouncements = $actor->canAccess('hr.announcements.manage');
        $canManageOnboarding = $actor->canAccessAny(['hr.onboarding.manage', 'hr.employees.manage']);
        $canManageEmployees = $actor->canAccess('hr.employees.manage');
        $canReviewComplaints = $actor->canAccess('complaints.review');

        $activeSection = $request->string('section')->toString() ?: 'dashboard';
        $allowedSections = ['dashboard'];

        if ($canManageOnboarding) {
            $allowedSections[] = 'onboarding';
        }

        if ($canManageAnnouncements) {
            $allowedSections[] = 'announcements';
        }

        if ($canManageEmployees) {
            $allowedSections[] = 'profile-requests';
        }

        if ($canReviewComplaints) {
            $allowedSections[] = 'complaints';
        }

        if (! in_array($activeSection, $allowedSections, true)) {
            $activeSection = 'dashboard';
        }

        $employees = $employees->get();
        $onboardingRequests = $canManageOnboarding
            ? EmployeeOnboardingRequest::with('department', 'systems.asset', 'requester', 'itCompleter')
                ->latest()
                ->take(20)
                ->get()
            : collect();
        $profileChangeRequests = $canManageEmployees
            ? ProfileChangeRequest::with('user.employee.department')->where('status', 'pending')->latest()->take(10)->get()
            : collect();
        $complaints = $complaints->get();

        return view('hr.index', [
            'activeSection' => $activeSection,
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(),
            'announcements' => Announcement::with('files')->latest()->take(8)->get(),
            'complaints' => $complaints,
            'onboardingRequests' => $onboardingRequests,
            'profileChangeRequests' => $profileChangeRequests,
            'employeeCount' => $employees->count(),
            'activeEmployeeCount' => $employees->where('is_active', true)->count(),
            'inactiveEmployeeCount' => $employees->where('is_active', false)->count(),
            'pendingOnboardingCount' => $onboardingRequests->where('status', '!=', 'hr_approved')->count(),
            'pendingProfileChangeCount' => $profileChangeRequests->count(),
            'complaintCount' => $complaints->count(),
            'canManageAnnouncements' => $canManageAnnouncements,
            'canManageOnboarding' => $canManageOnboarding,
            'canManageEmployees' => $canManageEmployees,
            'canReviewComplaints' => $canReviewComplaints,
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('hr.announcements.manage'), 403);

        $data = $request->validate([
            'announcement_no' => ['nullable', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:นโยบาย,ประกาศ,กิจกรรม'],
            'body' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_urgent' => ['nullable', 'boolean'],
            'popup_enabled' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $announcementData = collect($data)->except('files')->all();

        $announcement = Announcement::create([
            ...$announcementData,
            'announcement_no' => ($data['announcement_no'] ?? null) ?: $this->nextAnnouncementNo(),
            'created_by' => $request->user()->id,
            'department_id' => null,
            'is_pinned' => $request->boolean('is_pinned'),
            'is_urgent' => $request->boolean('is_urgent'),
            'popup_enabled' => $request->boolean('popup_enabled'),
            'published_at' => now(),
        ]);

        foreach ($request->file('files', []) as $file) {
            $path = $file->store('announcement-files');
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'file');

            AnnouncementFile::create([
                'announcement_id' => $announcement->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $extension,
                'file_size_kb' => (int) ceil($file->getSize() / 1024),
                'file_path' => $path,
            ]);
        }

        User::where('is_active', true)->get()->each(fn (User $user) => Notification::create([
            'user_id' => $user->id,
            'type' => 'announcement',
            'title' => 'ประกาศใหม่',
            'body' => $announcement->title,
            'url' => route('announcements.show', $announcement),
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

    public function reviewProfileChangeRequest(ProfileChangeRequest $profileChangeRequest, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccess('hr.employees.manage'), 403);
        abort_unless($profileChangeRequest->status === 'pending', 422);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $profileChangeRequest->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        if ($data['status'] === 'approved' && $profileChangeRequest->field === 'phone') {
            $profileChangeRequest->user->employee()->update([
                'phone' => $profileChangeRequest->requested_value,
            ]);
        }

        Notification::create([
            'user_id' => $profileChangeRequest->user_id,
            'type' => 'profile_change',
            'title' => $data['status'] === 'approved' ? 'HR อนุมัติการแก้เบอร์โทรแล้ว' : 'HR ไม่อนุมัติการแก้เบอร์โทร',
            'body' => $profileChangeRequest->requested_value,
            'url' => route('profile'),
        ]);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'review_profile_change',
            'subject_type' => ProfileChangeRequest::class,
            'subject_id' => $profileChangeRequest->id,
            'description' => "Reviewed {$profileChangeRequest->field}: {$data['status']}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'บันทึกผลอนุมัติข้อมูลพนักงานแล้ว');
    }

    public function updateEmployeeStatus(User $user, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccessAny(['hr.employees.manage', 'directory.manage']), 403);
        abort_if($actor->id === $user->id, 422, 'Cannot suspend your own account.');
        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);

        if (! $actor->canSeeAllData()) {
            abort_unless($actor->canSeeDepartmentData() && $actor->employee?->department_id === $user->employee?->department_id, 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        EmployeeDirectoryEntry::where('source_system', 'wdc')
            ->where('source_record_id', $user->employee_code)
            ->update([
                'is_active' => $user->is_active,
                'employment_status' => $user->is_active ? 'active' : 'resigned',
                'resigned_at' => $user->is_active ? null : now(),
            ]);

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

    private function nextAnnouncementNo(): string
    {
        return 'WDC-ANN-'.now()->format('Ymd-His');
    }
}
