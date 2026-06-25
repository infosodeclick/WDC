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
        'user_id',
        'source_record_id',
        'source_url',
        'image_url',
        'entry_type',
        'employment_status',
        'display_name',
        'english_name',
        'thai_name',
        'nickname',
        'english_nickname',
        'thai_nickname',
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
        'published_at',
        'resigned_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'imported_at' => 'datetime',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'resigned_at' => 'datetime',
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

    public function englishNickname(): ?string
    {
        if ($this->english_nickname) {
            return $this->english_nickname;
        }

        return $this->nickname && ! $this->containsThai($this->nickname)
            ? $this->nickname
            : null;
    }

    public function thaiNickname(): ?string
    {
        if ($this->thai_nickname) {
            return $this->thai_nickname;
        }

        return $this->nickname && $this->containsThai($this->nickname)
            ? $this->nickname
            : null;
    }

    public function englishDisplayNameWithNickname(): string
    {
        $name = $this->english_name ?: $this->display_name;
        $nickname = $this->englishNickname();

        return $nickname ? "{$name} ({$nickname})" : $name;
    }

    public function thaiDisplayNameWithNickname(): ?string
    {
        $name = $this->thai_name;
        $nickname = $this->thaiNickname();

        if (! $name && ! $nickname) {
            return null;
        }

        if ($name && $nickname) {
            return "{$name} ({$nickname})";
        }

        return $name ?: "({$nickname})";
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

    public function scopeVisibleInDirectory($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
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

    private function containsThai(string $value): bool
    {
        return preg_match('/[\x{0E00}-\x{0E7F}]/u', $value) === 1;
    }
}
