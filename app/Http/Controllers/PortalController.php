<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\EmployeeDirectoryEntry;
use App\Models\EmployeeDocument;
use App\Models\ItAsset;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeVideo;
use App\Models\MeetingRoomBooking;
use App\Models\Notification;
use App\Models\WorkflowRequest;
use App\Services\GoogleCalendarService;
use App\Services\ItHelpdeskWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function dashboard(Request $request, ItHelpdeskWorkflow $helpdesk): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');

        abort_unless($user->canAccess('portal.dashboard.view'), 403);

        $itRequests = $helpdesk->queryFor($user, false);

        return view('dashboard', [
            'user' => $user,
            'pinnedAnnouncements' => Announcement::with('department')->where('is_pinned', true)->latest('published_at')->take(4)->get(),
            'itRequests' => (clone $itRequests)->take(4)->get(),
            'itHelpdeskUrl' => $helpdesk->route(),
        ]);
    }

    public function profile(Request $request): View
    {
        abort_unless($request->user()->canAccess('profile.view'), 403);

        return view('profile.show', [
            'user' => $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department', 'employee.documents'),
        ]);
    }

    public function announcements(Request $request): View
    {
        abort_unless($request->user()->canAccess('announcements.view'), 403);

        $categories = collect(['นโยบาย', 'ประกาศ']);
        $activeCategory = $request->string('category')->toString();
        $query = Announcement::with('department', 'files', 'creator')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });

        if ($activeCategory !== '' && $categories->contains($activeCategory)) {
            $query->where('category', $activeCategory);
        } else {
            $activeCategory = '';
        }

        return view('announcements.index', [
            'announcements' => $query->orderByDesc('is_pinned')->latest('published_at')->paginate(10)->withQueryString(),
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function knowledge(Request $request): View
    {
        abort_unless($request->user()->canAccess('knowledge.view'), 403);

        $category = $request->string('category')->toString();
        $articles = KnowledgeArticle::where('is_published', true);
        $videos = KnowledgeVideo::where('is_published', true);

        if ($category !== '') {
            $articles->where('category', $category);
            $videos->where('category', $category);
        }

        return view('knowledge.index', [
            'articles' => $articles->latest('published_at')->get(),
            'videos' => $videos->latest('published_at')->get(),
            'categories' => KnowledgeArticle::select('category')->union(KnowledgeVideo::select('category'))->distinct()->orderBy('category')->pluck('category'),
            'activeCategory' => $category,
        ]);
    }

    public function documents(Request $request): View
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $employeeId = $user->employee?->id;
        $documents = EmployeeDocument::query();

        abort_unless($user->canAccess('documents.view'), 403);

        if ($user->canAccess('documents.manage') && $user->canSeeAllData()) {
            $documents->with('employee.user', 'employee.department');
        } elseif ($user->canAccess('documents.manage') && $user->canSeeDepartmentData() && $user->employee?->department_id) {
            $documents->where(function ($query) use ($employeeId, $user) {
                $query->where('is_company_wide', true)
                    ->orWhere('employee_id', $employeeId)
                    ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery->where('department_id', $user->employee->department_id));
            });
        } else {
            $documents->where(function ($query) use ($employeeId) {
                $query->where('is_company_wide', true)
                    ->orWhere('employee_id', $employeeId);
            });
        }

        return view('documents.index', [
            'documents' => $documents->latest()->get(),
        ]);
    }

    public function meetingRooms(Request $request): View
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides');

        abort_unless($user->canAccess('meeting_rooms.view'), 403);

        $bookingQuery = MeetingRoomBooking::with('user')
            ->where('user_id', $user->id)
            ->where('end_at', '>=', now()->subDay())
            ->orderBy('start_at')
            ->orderByDesc('created_at');

        return view('meeting-rooms.index', [
            'sheetUrl' => config('services.meeting_rooms.sheet_url'),
            'sheetEmbedUrl' => config('services.meeting_rooms.sheet_embed_url'),
            'bookingUrl' => config('services.meeting_rooms.booking_url'),
            'bookings' => $bookingQuery->take(10)->get(),
        ]);
    }

    public function storeMeetingRoomBooking(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        abort_unless($request->user()->canAccess('meeting_rooms.view'), 403);

        $data = $request->validate([
            'room_name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:160'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'attendees' => ['nullable', 'integer', 'min:1', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = MeetingRoomBooking::create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'submitted',
        ]);

        $statusMessage = 'จองห้องประชุมแล้ว และซิงค์เข้า Google Calendar แล้ว';

        try {
            $eventId = $calendar->createEvent($booking->loadMissing('user'));

            $booking->update([
                'status' => 'synced',
                'google_event_id' => $eventId,
                'synced_at' => now(),
                'sync_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $booking->update([
                'status' => 'sync_failed',
                'sync_error' => Str::limit($exception->getMessage(), 1000),
            ]);

            $statusMessage = 'บันทึกการจองใน WDC แล้ว แต่ยังซิงค์เข้า Google Calendar ไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า Google';
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_meeting_room_booking',
            'subject_type' => MeetingRoomBooking::class,
            'subject_id' => $booking->id,
            'description' => "Booked {$booking->room_name}: {$booking->title}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->to(route('meeting-rooms.index').'#wdc-bookings')
            ->with('status', $statusMessage);
    }

    public function cancelMeetingRoomBooking(MeetingRoomBooking $booking, Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides');

        abort_unless($user->canAccess('meeting_rooms.view'), 403);
        abort_unless($booking->user_id === $user->id || $user->canSeeAllData(), 403);

        $syncError = null;

        if ($booking->google_event_id) {
            try {
                $calendar->deleteEvent($booking->google_event_id);
            } catch (Throwable $exception) {
                report($exception);

                $syncError = Str::limit($exception->getMessage(), 1000);
            }
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'sync_error' => $syncError,
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'cancel_meeting_room_booking',
            'subject_type' => MeetingRoomBooking::class,
            'subject_id' => $booking->id,
            'description' => "Cancelled {$booking->room_name}: {$booking->title}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $message = $syncError
            ? 'ยกเลิกการจองใน WDC แล้ว แต่ลบออกจาก Google Calendar ไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า Google'
            : 'ยกเลิกการจองห้องประชุมแล้ว';

        return redirect()
            ->to(route('meeting-rooms.index').'#wdc-bookings')
            ->with('status', $message);
    }

    public function downloadDocument(EmployeeDocument $document, Request $request)
    {
        $user = $request->user()->load('role.permissions', 'permissionOverrides', 'employee.department');
        $employeeId = $user->employee?->id;

        abort_unless($user->canAccess('documents.view'), 403);
        abort_unless($document->is_company_wide || $document->employee_id === $employeeId || $this->canManageDocument($user, $document->load('employee.department')), 403);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'download_document',
            'subject_type' => EmployeeDocument::class,
            'subject_id' => $document->id,
            'description' => "Downloaded {$document->file_name}",
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $content = "WDC Portal Demo Document\n\nTitle: {$document->title}\nFile: {$document->file_name}\nCategory: {$document->category}\n\nThis placeholder download proves the document permission and download flow works.";

        return Response::make($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$document->file_name.'.txt"',
        ]);
    }

    public function payroll(): RedirectResponse
    {
        abort_unless(request()->user()?->canAccess('payroll.link'), 403);

        return redirect()->away(config('services.payroll.url', 'https://example.com/payroll'));
    }

    public function search(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $user = $request->user()->loadMissing('role.permissions', 'permissionOverrides');

        return view('search.index', [
            'q' => $q,
            'employees' => $q === '' || ! $user->canAccess('directory.view') ? collect() : Employee::with('user', 'department')
                ->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('employee_code', 'like', "%{$q}%"))
                ->orWhere('position', 'like', "%{$q}%")
                ->limit(8)
                ->get(),
            'directoryEntries' => $q === '' || ! $user->canAccess('directory.view') ? collect() : EmployeeDirectoryEntry::where('is_active', true)
                ->where(function ($query) use ($q) {
                    $query->where('display_name', 'like', "%{$q}%")
                        ->orWhere('english_name', 'like', "%{$q}%")
                        ->orWhere('thai_name', 'like', "%{$q}%")
                        ->orWhere('nickname', 'like', "%{$q}%")
                        ->orWhere('english_nickname', 'like', "%{$q}%")
                        ->orWhere('thai_nickname', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%")
                        ->orWhere('team', 'like', "%{$q}%")
                        ->orWhere('position', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->limit(8)
                ->get(),
            'workflowRequests' => $q === '' || ! $user->canAccessAny(['workflows.create', 'workflows.manage']) ? collect() : WorkflowRequest::with('template')
                ->where('requester_id', $request->user()->id)
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('details', 'like', "%{$q}%")
                        ->orWhere('legacy_reference', 'like', "%{$q}%");
                })
                ->limit(8)
                ->get(),
            'announcements' => $q === '' || ! $user->canAccess('announcements.view') ? collect() : Announcement::where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")->limit(8)->get(),
            'articles' => $q === '' || ! $user->canAccess('knowledge.view') ? collect() : KnowledgeArticle::where('title', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->limit(8)->get(),
            'videos' => $q === '' || ! $user->canAccess('knowledge.view') ? collect() : KnowledgeVideo::where('title', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->limit(8)->get(),
            'assets' => $q === '' || ! $user->canAccessItAssets() ? collect() : ItAsset::with('category', 'location')
                ->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('serial_number', 'like', "%{$q}%")
                        ->orWhere('owner_name', 'like', "%{$q}%");
                })
                ->limit(8)
                ->get(),
        ]);
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    private function canManageDocument($user, EmployeeDocument $document): bool
    {
        if (! $user->canAccess('documents.manage')) {
            return false;
        }

        if ($user->canSeeAllData()) {
            return true;
        }

        if ($user->canSeeDepartmentData() && $user->employee?->department_id) {
            return $document->employee?->department_id === $user->employee->department_id;
        }

        return false;
    }
}
