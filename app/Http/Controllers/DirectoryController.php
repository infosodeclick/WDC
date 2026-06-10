<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDirectoryEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $department = $request->string('department')->toString();
        $team = $request->string('team')->toString();
        $location = $request->string('location')->toString();
        $entryType = $request->string('type')->toString();

        $entries = EmployeeDirectoryEntry::where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('display_name', 'like', "%{$q}%")
                        ->orWhere('english_name', 'like', "%{$q}%")
                        ->orWhere('thai_name', 'like', "%{$q}%")
                        ->orWhere('nickname', 'like', "%{$q}%")
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
            ->when($entryType !== '', fn ($query) => $query->where('entry_type', $entryType))
            ->orderByRaw("CASE entry_type WHEN 'employee' THEN 1 WHEN 'mail_group' THEN 2 ELSE 3 END")
            ->orderBy('department')
            ->orderBy('display_name')
            ->paginate(18)
            ->withQueryString();

        return view('directory.index', [
            'entries' => $entries,
            'q' => $q,
            'department' => $department,
            'team' => $team,
            'location' => $location,
            'entryType' => $entryType,
            'departments' => EmployeeDirectoryEntry::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'teams' => EmployeeDirectoryEntry::whereNotNull('team')->distinct()->orderBy('team')->pluck('team'),
            'locations' => EmployeeDirectoryEntry::whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'entryTypes' => [
                'employee' => 'พนักงาน',
                'mail_group' => 'กลุ่มอีเมล',
                'showroom' => 'สาขา/โชว์รูม',
            ],
            'totalEntries' => EmployeeDirectoryEntry::where('is_active', true)->count(),
            'importedEntries' => EmployeeDirectoryEntry::where('is_active', true)->whereNotNull('source_record_id')->count(),
            'lastImportedAt' => EmployeeDirectoryEntry::whereNotNull('imported_at')->max('imported_at'),
        ]);
    }
}
