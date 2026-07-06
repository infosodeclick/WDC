<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDirectoryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('directory.view'), 403);

        $q = trim($request->string('q')->toString());
        $department = $request->string('department')->toString();
        $team = $request->string('team')->toString();
        $location = $request->string('location')->toString();
        $entryType = $request->string('type')->toString();
        $directoryView = $request->string('view')->toString();

        $directoryView = 'all';
        $allowedEntryTypes = ['employee', 'mail_group', 'showroom', 'resigned'];
        $entryType = in_array($entryType, $allowedEntryTypes, true) ? $entryType : '';
        $entryType = $entryType ?: 'employee';

        $visibleEntries = $entryType === 'resigned'
            ? EmployeeDirectoryEntry::query()
                ->where('entry_type', 'employee')
                ->where(function ($query) {
                    $query->where('employment_status', 'resigned')
                        ->orWhere('is_active', false);
                })
            : EmployeeDirectoryEntry::visibleInDirectory();

        $filteredEntries = (clone $visibleEntries)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('display_name', 'like', "%{$q}%")
                        ->orWhere('english_name', 'like', "%{$q}%")
                        ->orWhere('thai_name', 'like', "%{$q}%")
                        ->orWhere('nickname', 'like', "%{$q}%")
                        ->orWhere('english_nickname', 'like', "%{$q}%")
                        ->orWhere('thai_nickname', 'like', "%{$q}%")
                        ->orWhere('department', 'like', "%{$q}%")
                        ->orWhere('team', 'like', "%{$q}%")
                        ->orWhere('position', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('extension_number', 'like', "%{$q}%");
                });
            })
            ->when($department !== '', fn ($query) => $query->where('department', $department))
            ->when($team !== '', fn ($query) => $query->where('team', $team))
            ->when($location !== '', fn ($query) => $query->where('location', $location))
            ->when($entryType === 'showroom', fn ($query) => $query->where('entry_type', 'showroom')->where('display_name', 'like', '%Showroom%'))
            ->when(in_array($entryType, ['employee', 'mail_group'], true), fn ($query) => $query->where('entry_type', $entryType));

        $sortedEntries = (clone $filteredEntries)
            ->get()
            ->sortBy([
                fn (EmployeeDirectoryEntry $entry) => $entry->isNewHireThisMonth() ? 0 : 1,
                fn (EmployeeDirectoryEntry $entry) => $entry->isNewHireThisMonth() ? -optional($entry->startDate())->timestamp : 0,
                fn (EmployeeDirectoryEntry $entry) => ['employee' => 1, 'mail_group' => 2, 'showroom' => 3][$entry->entry_type] ?? 4,
                fn (EmployeeDirectoryEntry $entry) => $entry->department ?? '',
                fn (EmployeeDirectoryEntry $entry) => $entry->display_name,
            ])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 18;
        $entries = new LengthAwarePaginator(
            $sortedEntries->forPage($page, $perPage),
            $sortedEntries->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('directory.index', [
            'entries' => $entries,
            'directoryView' => $directoryView,
            'q' => $q,
            'department' => $department,
            'team' => $team,
            'location' => $location,
            'entryType' => $entryType,
            'departments' => (clone $visibleEntries)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'teams' => (clone $visibleEntries)->whereNotNull('team')->distinct()->orderBy('team')->pluck('team'),
            'locations' => (clone $visibleEntries)->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'entryTypes' => [
                'employee' => 'พนักงาน',
                'mail_group' => 'กลุ่มอีเมล',
                'showroom' => 'สาขา/โชว์รูม',
                'resigned' => 'พนักงานที่ลาออก',
            ],
            'canManageDirectory' => $request->user()->canAccess('directory.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAccess('directory.manage'), 403);

        $validated = $request->validate([
            'entry_type' => ['required', Rule::in(['employee', 'mail_group', 'showroom'])],
            'display_name' => ['required', 'string', 'max:255'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'thai_name' => ['nullable', 'string', 'max:255'],
            'english_nickname' => ['nullable', 'string', 'max:100'],
            'thai_nickname' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'extension_number' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated = array_merge([
            'english_name' => null,
            'thai_name' => null,
            'english_nickname' => null,
            'thai_nickname' => null,
            'department' => null,
            'team' => null,
            'position' => null,
            'location' => null,
            'email' => null,
            'phone' => null,
            'extension_number' => null,
            'image_url' => null,
            'notes' => null,
        ], $validated);

        if ($validated['entry_type'] === 'mail_group') {
            $validated['department'] = $validated['department'] ?: 'Mail Group';
            $validated['position'] = $validated['position'] ?: 'Mail Group';
        }

        if ($validated['entry_type'] === 'showroom') {
            $validated['department'] = 'Showroom';
            $validated['position'] = $validated['position'] ?: 'Showroom';
        }

        EmployeeDirectoryEntry::create([
            ...$validated,
            'source_system' => 'wdc_manual',
            'source_record_id' => (string) Str::uuid(),
            'employment_status' => 'active',
            'imported_at' => now(),
            'is_active' => true,
            'published_at' => now(),
            'raw_payload' => [
                'created_from' => 'directory.store',
                'created_by' => $request->user()->employee_code,
            ],
        ]);

        return redirect()
            ->route('directory.index', ['type' => $validated['entry_type']])
            ->with('status', 'เพิ่มข้อมูลรายชื่อเรียบร้อยแล้ว');
    }
}
