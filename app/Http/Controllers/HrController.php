<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Models\Complaint;
use App\Models\Department;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeOffboardingRequest;
use App\Models\EmployeeOnboardingRequest;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccessAny(['hr.portal.view', 'hr.employees.manage', 'hr.announcements.manage', 'complaints.review']), 403);

        $employeeSearch = trim($request->string('employee_q')->toString());
        $employeeUsers = $this->employeeListQuery($actor)->get();
        $employees = $this->employeeDirectoryQuery($actor, $employeeSearch)->get();

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

        if ($canManageEmployees) {
            $allowedSections[] = 'employees';
            $allowedSections[] = 'offboarding';
        }

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

        $employeeRows = $this->employeeExportRows($employees);
        $onboardingRequests = $canManageOnboarding
            ? EmployeeOnboardingRequest::with('department', 'systems.asset', 'requester', 'itCompleter')
                ->latest()
                ->take(20)
                ->get()
            : collect();
        $offboardingRequests = $canManageEmployees
            ? EmployeeOffboardingRequest::with('systems.asset', 'employeeUser.employee.department', 'requester', 'claimedBy', 'itCompleter')
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
            'employeeUsers' => $employeeUsers,
            'employeeSearch' => $employeeSearch,
            'employeeRows' => $employeeRows,
            'departments' => Department::orderBy('name')->get(),
            'onboardingPositions' => $this->onboardingPositions(),
            'onboardingTeams' => $this->onboardingTeams(),
            'onboardingLocations' => $this->onboardingLocations(),
            'onboardingSalesAssignments' => $this->onboardingSalesAssignments(),
            'onboardingDeskPhones' => $this->onboardingDeskPhones(),
            'announcements' => Announcement::with('files')->latest()->take(8)->get(),
            'complaints' => $complaints,
            'onboardingRequests' => $onboardingRequests,
            'offboardingRequests' => $offboardingRequests,
            'profileChangeRequests' => $profileChangeRequests,
            'employeeCount' => $employees->count(),
            'activeEmployeeCount' => $employees->where('is_active', true)->count(),
            'inactiveEmployeeCount' => $employees->where('is_active', false)->count(),
            'pendingOnboardingCount' => $onboardingRequests->where('status', '!=', 'hr_approved')->count(),
            'pendingOffboardingCount' => $offboardingRequests->where('status', '!=', 'hr_approved')->count(),
            'pendingProfileChangeCount' => $profileChangeRequests->count(),
            'complaintCount' => $complaints->count(),
            'canManageAnnouncements' => $canManageAnnouncements,
            'canManageOnboarding' => $canManageOnboarding,
            'canManageEmployees' => $canManageEmployees,
            'canReviewComplaints' => $canReviewComplaints,
        ]);
    }

    public function exportEmployees(Request $request): StreamedResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccess('hr.employees.manage'), 403);

        $format = $request->string('format')->lower()->toString();
        $employeeSearch = trim($request->string('employee_q')->toString());
        $employees = $this->employeeDirectoryQuery($actor, $employeeSearch)->get();
        $rows = $this->employeeExportRows($employees);

        return $format === 'csv'
            ? $this->streamEmployeeCsv($rows)
            : $this->streamEmployeeExcel($rows);
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

        app(PortalNotificationService::class)->createForUsers(User::where('is_active', true)->get(), [
            'type' => 'announcement',
            'title' => 'ประกาศใหม่',
            'body' => $announcement->title,
            'url' => route('announcements.show', $announcement),
        ]);

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

        app(PortalNotificationService::class)->createForUser($profileChangeRequest->user, [
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

    public function updateDirectoryEntry(EmployeeDirectoryEntry $directoryEntry, Request $request): RedirectResponse
    {
        $actor = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($actor->canAccessAny(['hr.employees.manage', 'directory.manage']), 403);
        abort_unless($directoryEntry->entry_type === 'employee', 403);
        abort_unless($this->canAccessDirectoryEntry($actor, $directoryEntry), 403);

        $validated = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'english_nickname' => ['nullable', 'string', 'max:100'],
            'thai_name' => ['nullable', 'string', 'max:255'],
            'thai_nickname' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'extension_number' => ['nullable', 'string', 'max:100'],
            'employment_status' => ['required', Rule::in(['active', 'resigned'])],
        ]);

        $rawPayload = $directoryEntry->raw_payload ?: [];
        $rawPayload['employee_code'] = $validated['employee_code'] ?? null;
        $rawPayload['start_date'] = $validated['start_date'] ?? null;

        $directoryEntry->update([
            'display_name' => $validated['english_name'] ?: $directoryEntry->display_name,
            'english_name' => $validated['english_name'] ?? null,
            'english_nickname' => $validated['english_nickname'] ?? null,
            'thai_name' => $validated['thai_name'] ?? null,
            'thai_nickname' => $validated['thai_nickname'] ?? null,
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'team' => $validated['team'] ?? null,
            'location' => $validated['location'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'extension_number' => $validated['extension_number'] ?? null,
            'employment_status' => $validated['employment_status'],
            'is_active' => $validated['employment_status'] === 'active',
            'resigned_at' => $validated['employment_status'] === 'resigned' ? ($directoryEntry->resigned_at ?: now()) : null,
            'raw_payload' => $rawPayload,
        ]);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => 'update_directory_employee',
            'subject_type' => EmployeeDirectoryEntry::class,
            'subject_id' => $directoryEntry->id,
            'description' => "Updated directory employee {$directoryEntry->display_name}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return back()->with('status', 'บันทึกข้อมูลรายชื่อพนักงานแล้ว');
    }

    private function employeeListQuery(User $actor)
    {
        $employees = User::with('role', 'employee.department')
            ->where('employee_code', '!=', 'administrator')
            ->orderBy('employee_code');

        if (! $actor->canSeeAllData()) {
            if ($actor->canSeeDepartmentData() && $actor->employee?->department_id) {
                $employees->whereHas('employee', fn ($query) => $query->where('department_id', $actor->employee->department_id));
            } else {
                $employees->where('id', $actor->id);
            }
        }

        return $employees;
    }

    private function employeeDirectoryQuery(User $actor, string $search = '')
    {
        $entries = EmployeeDirectoryEntry::query()
            ->where('entry_type', 'employee')
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('display_name');

        if ($search !== '') {
            $like = "%{$search}%";

            $entries->where(function ($query) use ($like) {
                $query->where('source_record_id', 'like', $like)
                    ->orWhere('display_name', 'like', $like)
                    ->orWhere('english_name', 'like', $like)
                    ->orWhere('english_nickname', 'like', $like)
                    ->orWhere('thai_name', 'like', $like)
                    ->orWhere('thai_nickname', 'like', $like)
                    ->orWhere('nickname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('extension_number', 'like', $like)
                    ->orWhere('raw_payload->employee_code', 'like', $like);
            });
        }

        if (! $actor->canSeeAllData()) {
            if ($actor->canSeeDepartmentData() && $actor->employee?->department?->name) {
                $entries->where('department', $actor->employee->department->name);
            } else {
                $entries->where('user_id', $actor->id);
            }
        }

        return $entries;
    }

    private function canAccessDirectoryEntry(User $actor, EmployeeDirectoryEntry $entry): bool
    {
        if ($actor->canSeeAllData()) {
            return true;
        }

        if ($actor->canSeeDepartmentData() && $actor->employee?->department?->name) {
            return $entry->department === $actor->employee->department->name;
        }

        return $entry->user_id === $actor->id;
    }

    private function employeeExportRows(Collection $employees): Collection
    {
        return $employees->map(fn (EmployeeDirectoryEntry $entry) => [
            'รหัสพนักงาน' => $entry->employeeCode(),
            'วันที่เริ่มงาน' => $entry->startDate()?->format('Y-m-d'),
            'ชื่ออังกฤษ' => $entry->english_name ?: $entry->display_name,
            'ชื่อเล่นอังกฤษ' => $entry->englishNickname(),
            'ชื่อไทย' => $entry->thai_name,
            'ชื่อเล่นไทย' => $entry->thaiNickname(),
            'ตำแหน่ง' => $entry->position,
            'แผนก/BU' => $entry->department,
            'ทีม' => $entry->team,
            'สาขา' => $entry->location,
            'อีเมล' => $entry->email,
            'โทร' => $entry->phone,
            'เบอร์โต๊ะ' => $entry->extension_number,
            'สถานะ' => $entry->is_active ? 'ใช้งานอยู่' : 'ไม่แสดง/ลาออก',
        ]);
    }

    private function streamEmployeeCsv(Collection $rows): StreamedResponse
    {
        $filename = 'wdc-employees-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            $headers = array_keys($rows->first() ?? []);
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function streamEmployeeExcel(Collection $rows): StreamedResponse
    {
        $filename = 'wdc-employees-'.now()->format('Ymd-His').'.xls';

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
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function nextAnnouncementNo(): string
    {
        return 'WDC-ANN-'.now()->format('Ymd-His');
    }

    private function onboardingPositions(): array
    {
        return [
            'Sales Admin Executive',
            'Sales Executive',
            'Sales Showroom',
            'Sales Supervisor',
            'Accounting Officer',
            'HR Officer',
            'IT Support',
            'Warehouse Officer',
            'Purchasing Officer',
            'Marketing Officer',
            'Driver Executive',
            'Maid',
            'System Administrator',
        ];
    }

    private function onboardingTeams(): array
    {
        return [
            'Management',
            'People Operations',
            'IT Support',
            'Sales Team',
            'Bangkok Project (Team A)',
            'Bangkok Project (Team B)',
            'Bangkok Project (Team C)',
            'Bangkok Project (Team G)',
            'BANGKOK',
            'PATTAYA',
            'KHONKAEN',
            'KORAT',
            'CHIANG MAI',
            'PHITSANULOK',
            'PHUKET',
            'SURAT',
            'HATYAI',
            'PRACHUAP KHIRI KHAN',
            'Showroom CDC',
            'Showroom Ratchada',
            'Showroom Nimitmai',
            'Showroom Pattaya',
            'Showroom Phuket',
            'Showroom Hatyai',
            'Showroom Surat thani',
            'Showroom Chiang Mai',
            'Showroom Khon Kean',
            'North',
            'Upper North East (NE1)',
            'Lower North East (NE2)',
            'Central',
            'South',
            'ระบุทีมเอง',
            'Accounting',
            'Warehouse',
            'Purchasing',
            'Marketing',
        ];
    }

    private function onboardingLocations(): array
    {
        return [
            'Lumpini',
            'Nimitmai',
            'Bangkok Project',
            'Showroom CDC',
            'Showroom Ratchada',
            'Showroom Nimitmai',
            'Showroom Pattaya',
            'Showroom Phuket',
            'Showroom Hatyai',
            'Showroom Surat thani',
            'Showroom Chiang Mai',
            'Showroom Khon Kean',
            'BANGKOK',
            'PATTAYA',
            'KHONKAEN',
            'KORAT',
            'CHIANG MAI',
            'PHITSANULOK',
            'PHUKET',
            'SURAT',
            'HATYAI',
            'PRACHUAP KHIRI KHAN',
            'North',
            'Upper North East (NE1)',
            'Lower North East (NE2)',
            'Central',
            'South',
            'Warehouse',
            'All Place',
        ];
    }

    private function onboardingSalesAssignments(): array
    {
        $salesDepartmentId = Department::where('code', 'SALES')->value('id');

        $rows = [
            ['Sales Admin Executive', 'Admin', 'ระบุทีมเอง', '-'],
            ['Sales Executive', 'Bangkok Project (BU2)', 'Bangkok Project (Team A)', '-'],
            ['Sales Executive', 'Bangkok Project (BU2)', 'Bangkok Project (Team B)', '-'],
            ['Sales Executive', 'Bangkok Project (BU2)', 'Bangkok Project (Team C)', '-'],
            ['Sales Executive', 'Bangkok Project (BU2)', 'Bangkok Project (Team G)', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'BANGKOK', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'PATTAYA', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'KHONKAEN', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'KORAT', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'CHIANG MAI', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'PHITSANULOK', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'PHUKET', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'SURAT', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'HATYAI', '-'],
            ['Sales Executive', 'Local Project (BU5)', 'PRACHUAP KHIRI KHAN', '-'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom CDC', 'Showroom CDC'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Ratchada', 'Showroom Ratchada'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Nimitmai', 'Showroom Nimitmai'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Pattaya', 'Showroom Pattaya'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Phuket', 'Showroom Phuket'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Hatyai', 'Showroom Hatyai'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Surat thani', 'Showroom Surat thani'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Chiang Mai', 'Showroom Chiang Mai'],
            ['Sales Showroom', 'Retail (BU3)', 'Showroom Khon Kean', 'Showroom Khon Kean'],
            ['Sales Executive', 'Modern Trade', '-', '-'],
            ['Sales Showroom', 'W Ceramic', 'Nimitmai', 'Nimitmai'],
            ['Sales Showroom', 'W Ceramic', 'Nakhon Pathom', 'Nakhon Pathom'],
            ['Sales Executive', 'W Ceramic Project', '-', '-'],
            ['Sales Executive', 'Traditional Trade', 'North', '-'],
            ['Sales Executive', 'Traditional Trade', 'Upper North East (NE1)', '-'],
            ['Sales Executive', 'Traditional Trade', 'Lower North East (NE2)', '-'],
            ['Sales Executive', 'Traditional Trade', 'Central', '-'],
            ['Sales Executive', 'Traditional Trade', 'South', '-'],
            ['Sales Executive', 'Sanitaryware (BU4)', '-', '-'],
            ['Sales Executive', 'Big Slab, European Tiles, Non tiles (BU6)', '-', '-'],
            ['Sales Supervisor', 'ระบุทีมเอง', 'ระบุทีมเอง', '-'],
        ];

        return collect($rows)->map(fn (array $row) => [
            'position' => $row[0],
            'business_unit' => $row[1],
            'team' => $row[2],
            'location' => $row[3],
            'department_id' => $salesDepartmentId,
        ])->all();
    }

    private function onboardingDeskPhones(): array
    {
        return [
            '1800',
            '1801',
            '1802',
            '1803',
            '1804',
            '2101',
            '2102',
            '2103',
            '8000',
            '8001',
            '8002',
            '8003',
            '8004',
        ];
    }
}
