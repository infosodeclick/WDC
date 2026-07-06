<?php

namespace App\Services;

use App\Models\Department;
use App\Models\EmployeeDirectoryEntry;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class DirectoryUserSyncService
{
    /**
     * @return array<string, mixed>
     */
    public function syncFromXlsx(string $path, bool $commit = false, string $defaultPassword = 'Wdc@2026'): array
    {
        $excelRows = $this->readEmployeeCodeRows($path);
        $index = $this->buildExcelIndex($excelRows);
        $employeeRole = Role::where('slug', 'employee')->firstOrFail();
        $hrRole = Role::where('slug', 'hr')->first();
        $itRole = Role::where('slug', 'it_support')->first();

        $stats = [
            'directory_employees' => 0,
            'matched' => 0,
            'created_users' => 0,
            'updated_users' => 0,
            'linked_directory_entries' => 0,
            'role_updates' => 0,
            'skipped_without_excel_code' => 0,
            'skipped_admin' => 0,
            'skipped_conflict' => 0,
            'dry_run' => ! $commit,
            'samples' => [],
        ];

        $entries = EmployeeDirectoryEntry::query()
            ->where('entry_type', 'employee')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('employment_status')
                    ->orWhereNotIn('employment_status', ['resigned', 'inactive']);
            })
            ->orderBy('display_name')
            ->get();

        $stats['directory_employees'] = $entries->count();

        $work = function () use ($entries, $index, $employeeRole, $hrRole, $itRole, $defaultPassword, &$stats): void {
            foreach ($entries as $entry) {
                $match = $this->matchExcelRow($entry, $index);

                if (! $match) {
                    $stats['skipped_without_excel_code']++;
                    continue;
                }

                $employeeCode = $match['employee_code'];

                if ($this->isProtectedEmployeeCode($employeeCode)) {
                    $stats['skipped_admin']++;
                    continue;
                }

                $stats['matched']++;
                $role = $this->roleForEntry($entry, $match, $employeeRole, $hrRole, $itRole);
                $user = $this->findUserForEntry($entry, $employeeCode);

                if ($user && $this->isProtectedUser($user)) {
                    $stats['skipped_admin']++;
                    continue;
                }

                $email = $this->uniqueEmailForUser($entry->email, $user);

                if (! $user && User::where('employee_code', $employeeCode)->exists()) {
                    $stats['skipped_conflict']++;
                    continue;
                }

                if (! $user) {
                    $user = User::create([
                        'role_id' => $role->id,
                        'employee_code' => $employeeCode,
                        'name' => $entry->english_name ?: $entry->display_name,
                        'email' => $email,
                        'password' => Hash::make($defaultPassword),
                        'is_active' => true,
                    ]);
                    $stats['created_users']++;
                } else {
                    $payload = [
                        'employee_code' => $employeeCode,
                        'name' => $entry->english_name ?: $entry->display_name ?: $user->name,
                        'email' => $email,
                        'is_active' => true,
                    ];

                    if ($this->shouldApplySpecialRole($user, $role)) {
                        $payload['role_id'] = $role->id;
                        $stats['role_updates']++;
                    }

                    $user->update($payload);
                    $stats['updated_users']++;
                }

                $department = $this->departmentFor($entry->department ?: $match['department']);
                $user->employee()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'department_id' => $department->id,
                        'english_name' => $entry->english_name ?: $match['english_name'],
                        'thai_name' => $entry->thai_name ?: $match['thai_name'],
                        'nickname' => $entry->thai_nickname ?: $entry->nickname ?: $match['nickname'],
                        'english_nickname' => $entry->english_nickname,
                        'thai_nickname' => $entry->thai_nickname ?: $entry->nickname ?: $match['nickname'],
                        'position' => $entry->position ?: $match['position'] ?: '-',
                        'business_unit' => $entry->department ?: $match['department'],
                        'team' => $entry->team,
                        'location' => $entry->location,
                        'phone' => $entry->phone,
                        'extension_number' => $entry->extension_number,
                        'start_date' => $entry->startDate()?->toDateString() ?: $match['start_date'],
                    ],
                );

                $rawPayload = $entry->raw_payload ?: [];
                $entry->update([
                    'user_id' => $user->id,
                    'raw_payload' => [
                        ...$rawPayload,
                        'employee_code' => $employeeCode,
                        'wdc_login_synced_at' => now()->toDateTimeString(),
                    ],
                ]);
                $stats['linked_directory_entries']++;

                if (count($stats['samples']) < 8) {
                    $stats['samples'][] = "{$employeeCode} {$user->name} -> {$user->role?->name}";
                }
            }
        };

        if ($commit) {
            DB::transaction($work);
        } else {
            DB::beginTransaction();
            try {
                $work();
            } finally {
                DB::rollBack();
            }
        }

        return $stats;
    }

    /**
     * @return list<array<string, ?string>>
     */
    public function readEmployeeCodeRows(string $path): array
    {
        $rows = $this->readWorksheetRows($path);
        $header = [];
        $mapped = [];

        foreach ($rows as $row) {
            $values = array_map(fn (?string $value) => trim((string) $value), $row);

            if ($header === []) {
                $header = $values;
                continue;
            }

            if (collect($values)->filter()->isEmpty()) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $column) {
                $assoc[$this->normalizeHeader($column)] = $values[$index] ?? null;
            }

            $code = $this->normalizeEmployeeCode($assoc['staffid'] ?? $assoc['employeeid'] ?? $assoc['รหัสพนักงาน'] ?? null);

            if (! $code) {
                continue;
            }

            $mapped[] = [
                'employee_code' => $code,
                'english_name' => $assoc['ชื่อสกุลeng'] ?? $assoc['nameeng'] ?? $assoc['englishname'] ?? null,
                'thai_name' => $assoc['ชื่อนามสกุลไทย'] ?? $assoc['ชื่อสกุลไทย'] ?? $assoc['thainame'] ?? null,
                'nickname' => $assoc['ชื่อเล่น'] ?? $assoc['nickname'] ?? null,
                'department' => $assoc['department'] ?? $assoc['แผนก'] ?? null,
                'position' => $assoc['position'] ?? $assoc['ตำแหน่ง'] ?? null,
                'start_date' => $this->normalizeDate($assoc['startdate'] ?? $assoc['วันที่เริ่มงาน'] ?? null),
            ];
        }

        return $mapped;
    }

    /**
     * @param list<array<string, ?string>> $rows
     * @return array<string, array<string, array<string, ?string>|list<array<string, ?string>>>>
     */
    private function buildExcelIndex(array $rows): array
    {
        $index = [
            'english' => [],
            'thai' => [],
        ];

        foreach ($rows as $row) {
            foreach (['english' => 'english_name', 'thai' => 'thai_name'] as $bucket => $field) {
                $key = $this->normalizeName($row[$field] ?? null);

                if ($key === '') {
                    continue;
                }

                $index[$bucket][$key] ??= [];
                $index[$bucket][$key][] = $row;
            }
        }

        return $index;
    }

    /**
     * @param array<string, array<string, list<array<string, ?string>>>> $index
     * @return array<string, ?string>|null
     */
    private function matchExcelRow(EmployeeDirectoryEntry $entry, array $index): ?array
    {
        foreach ([
            ['english', $entry->english_name],
            ['english', $entry->display_name],
            ['thai', $entry->thai_name],
        ] as [$bucket, $name]) {
            $key = $this->normalizeName($name);

            if ($key === '' || ! isset($index[$bucket][$key]) || count($index[$bucket][$key]) !== 1) {
                continue;
            }

            return $index[$bucket][$key][0];
        }

        return null;
    }

    private function findUserForEntry(EmployeeDirectoryEntry $entry, string $employeeCode): ?User
    {
        $user = User::where('employee_code', $employeeCode)->first();

        if ($user) {
            return $user;
        }

        if ($entry->user_id) {
            return User::find($entry->user_id);
        }

        if ($entry->email) {
            return User::where('email', $entry->email)->first();
        }

        return null;
    }

    /**
     * @param array<string, ?string> $match
     */
    private function roleForEntry(EmployeeDirectoryEntry $entry, array $match, Role $employeeRole, ?Role $hrRole, ?Role $itRole): Role
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $entry->department,
            $entry->team,
            $entry->position,
            $match['department'] ?? null,
            $match['position'] ?? null,
        ])));

        if ($hrRole && (str_contains($text, 'human resources') || str_contains($text, ' hr ') || str_contains($text, 'ทรัพยากร'))) {
            return $hrRole;
        }

        if ($itRole && (str_contains($text, 'information technology') || str_contains($text, ' it ') || str_contains($text, 'เทคโนโลยีสารสนเทศ'))) {
            return $itRole;
        }

        return $employeeRole;
    }

    private function shouldApplySpecialRole(User $user, Role $role): bool
    {
        if ($this->isProtectedUser($user)) {
            return false;
        }

        return in_array($role->slug, ['hr', 'it_support'], true)
            && $user->role?->slug === 'employee'
            && $user->role_id !== $role->id;
    }

    private function departmentFor(?string $name): Department
    {
        $name = trim((string) $name) ?: 'General';
        $code = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(40, '')
            ->toString() ?: 'GENERAL';

        return Department::firstOrCreate(
            ['code' => $code],
            ['name' => $name],
        );
    }

    private function uniqueEmailForUser(?string $email, ?User $user): ?string
    {
        $email = trim((string) $email);

        if ($email === '') {
            return null;
        }

        $existing = User::where('email', $email)
            ->when($user, fn ($query) => $query->whereKeyNot($user->id))
            ->exists();

        return $existing ? null : $email;
    }

    private function normalizeEmployeeCode(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.0+)?$/', $value)) {
            $value = (string) (int) $value;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[\s\-\(\)\/.]+/u', '')
            ->toString();
    }

    private function normalizeName(?string $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }

            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isProtectedEmployeeCode(string $employeeCode): bool
    {
        return in_array(mb_strtolower($employeeCode), ['administrator', 'admin', 'emp09999'], true);
    }

    private function isProtectedUser(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole('admin')
            || in_array(mb_strtolower($user->employee_code), ['administrator', 'emp09999'], true);
    }

    /**
     * @return list<list<?string>>
     */
    private function readWorksheetRows(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Spreadsheet not found: {$path}");
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException("Cannot open spreadsheet: {$path}");
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException('Cannot read first worksheet.');
            }

            $xml = new SimpleXMLElement($sheetXml);
            $xml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];

            foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $cells = [];

                foreach ($row->xpath('*[local-name()="c"]') ?: [] as $cell) {
                    $reference = (string) $cell['r'];
                    $column = $this->columnIndex($reference);
                    $cells[$column] = $this->cellValue($cell, $sharedStrings);
                }

                if ($cells === []) {
                    continue;
                }

                $max = max(array_keys($cells));
                $rows[] = array_map(fn (int $index) => $cells[$index] ?? null, range(0, $max));
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = new SimpleXMLElement($xml);
        $shared->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];

        foreach ($shared->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $textParts = [];

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $textParts[] = (string) $text;
            }

            $strings[] = implode('', $textParts);
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                return $name;
            }
        }

        throw new RuntimeException('Spreadsheet has no worksheet.');
    }

    /**
     * @param list<string> $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            $index = (int) ($cell->v ?? -1);

            return $sharedStrings[$index] ?? null;
        }

        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $parts[] = (string) $text;
            }

            return implode('', $parts);
        }

        return isset($cell->v) ? (string) $cell->v : null;
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
