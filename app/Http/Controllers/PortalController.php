<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeVideo;
use App\Models\LegacySystem;
use App\Models\Notification;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user()->load('role', 'employee.department');

        return view('dashboard', [
            'user' => $user,
            'newAnnouncements' => Announcement::where('published_at', '>=', now()->subDays(7))->count(),
            'pendingTickets' => Ticket::where('reporter_id', $user->id)->whereNotIn('status', ['done'])->count(),
            'newVideos' => KnowledgeVideo::where('published_at', '>=', now()->subDays(14))->count(),
            'pinnedAnnouncements' => Announcement::with('department')->where('is_pinned', true)->latest('published_at')->take(4)->get(),
            'tickets' => Ticket::with('assignee')->where('reporter_id', $user->id)->latest()->take(4)->get(),
            'videos' => KnowledgeVideo::latest('published_at')->take(3)->get(),
            'featuredSystems' => LegacySystem::where('is_featured', true)->orderBy('sort_order')->take(4)->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user()->load('role', 'employee.department', 'employee.documents', 'externalAccounts.legacySystem'),
        ]);
    }

    public function systems(Request $request): View
    {
        $user = $request->user()->load('externalAccounts.legacySystem');

        return view('systems.index', [
            'systems' => LegacySystem::with(['accounts' => fn ($query) => $query->where('user_id', $user->id)])
                ->orderBy('sort_order')
                ->get(),
            'user' => $user,
        ]);
    }

    public function announcements(Request $request): View
    {
        $query = Announcement::with('department', 'files', 'creator')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return view('announcements.index', [
            'announcements' => $query->orderByDesc('is_pinned')->latest('published_at')->paginate(10)->withQueryString(),
            'categories' => Announcement::distinct()->orderBy('category')->pluck('category'),
            'activeCategory' => $request->string('category')->toString(),
        ]);
    }

    public function knowledge(Request $request): View
    {
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
        $employeeId = $request->user()->employee?->id;

        return view('documents.index', [
            'documents' => EmployeeDocument::where('is_company_wide', true)
                ->orWhere('employee_id', $employeeId)
                ->latest()
                ->get(),
        ]);
    }

    public function downloadDocument(EmployeeDocument $document, Request $request)
    {
        $employeeId = $request->user()->employee?->id;

        abort_unless($document->is_company_wide || $document->employee_id === $employeeId || $request->user()->hasAnyRole(['hr', 'admin']), 403);

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
        return redirect()->away(config('services.payroll.url', 'https://example.com/payroll'));
    }

    public function search(Request $request): View
    {
        $q = trim($request->string('q')->toString());

        return view('search.index', [
            'q' => $q,
            'employees' => $q === '' ? collect() : Employee::with('user', 'department')
                ->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('employee_code', 'like', "%{$q}%"))
                ->orWhere('position', 'like', "%{$q}%")
                ->limit(8)
                ->get(),
            'announcements' => $q === '' ? collect() : Announcement::where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")->limit(8)->get(),
            'articles' => $q === '' ? collect() : KnowledgeArticle::where('title', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->limit(8)->get(),
            'videos' => $q === '' ? collect() : KnowledgeVideo::where('title', 'like', "%{$q}%")->orWhere('summary', 'like', "%{$q}%")->limit(8)->get(),
        ]);
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'อ่านแจ้งเตือนทั้งหมดแล้ว');
    }
}
