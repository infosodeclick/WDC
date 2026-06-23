<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDirectoryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'source_record_id',
        'source_url',
        'image_url',
        'entry_type',
        'display_name',
        'english_name',
        'thai_name',
        'nickname',
        'department',
        'team',
        'position',
        'location',
        'email',
        'phone',
        'extension_number',
        'notes',
        'raw_payload',
        'imported_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'imported_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function entryTypeLabel(): string
    {
        return [
            'employee' => 'พนักงาน',
            'mail_group' => 'กลุ่มอีเมล',
            'showroom' => 'สาขา/โชว์รูม',
        ][$this->entry_type] ?? $this->entry_type;
    }

    public function avatarInitials(): string
    {
        $name = trim($this->thai_name ?: $this->display_name);

        if ($name === '') {
            return 'W';
        }

        $parts = preg_split('/\s+/u', $name) ?: [$name];
        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_substr($part, 0, 1, 'UTF-8'))
            ->implode('');

        return $letters !== '' ? $letters : 'W';
    }

    public function employeeCode(): ?string
    {
        $payload = $this->raw_payload ?: [];

        foreach (['employee_code', 'Employee Code', 'รหัสพนักงาน', 'รหัส', 'code'] as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public function startDate(): ?CarbonImmutable
    {
        $payload = $this->raw_payload ?: [];

        foreach ([
            'start_date',
            'hire_date',
            'hired_at',
            'employment_start_date',
            'first_working_date',
            'date_start',
            'Start Date',
            'Hire Date',
            'Employment Start Date',
            'First Working Date',
            'วันเริ่มงาน',
            'วันที่เริ่มงาน',
            'เริ่มงาน',
        ] as $key) {
            $date = $this->parseDirectoryDate(data_get($payload, $key));

            if ($date) {
                return $date;
            }
        }

        return null;
    }

    public function isNewHireThisMonth(): bool
    {
        $startDate = $this->startDate();

        return $this->entry_type === 'employee'
            && $startDate !== null
            && $startDate->isSameMonth(now())
            && $startDate->isSameYear(now());
    }

    /**
     * @param mixed $value
     */
    private function parseDirectoryDate($value): ?CarbonImmutable
    {
        if (is_array($value)) {
            foreach (['date', 'start', 'value', 'plain_text', 'text'] as $key) {
                $date = $this->parseDirectoryDate($value[$key] ?? null);

                if ($date) {
                    return $date;
                }
            }

            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
