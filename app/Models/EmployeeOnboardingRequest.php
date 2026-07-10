<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOnboardingRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending_it', 'in_progress', 'it_completed', 'hr_approved', 'cancel_requested', 'cancelled'];

    protected $fillable = [
        'requested_by',
        'claimed_by_id',
        'it_completed_by',
        'hr_approved_by',
        'cancel_requested_by',
        'cancel_confirmed_by',
        'user_id',
        'directory_entry_id',
        'department_id',
        'employee_code',
        'english_name',
        'thai_name',
        'english_nickname',
        'thai_nickname',
        'position',
        'business_unit',
        'team',
        'location',
        'corporate_email',
        'personal_phone',
        'extension_number',
        'start_date',
        'photo_path',
        'status',
        'hr_note',
        'it_note',
        'cancel_reason',
        'claimed_at',
        'it_completed_at',
        'hr_approved_at',
        'cancel_requested_at',
        'cancel_confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'claimed_at' => 'datetime',
            'it_completed_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancel_confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function itCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'it_completed_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function cancelRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function cancelConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_confirmed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function directoryEntry(): BelongsTo
    {
        return $this->belongsTo(EmployeeDirectoryEntry::class, 'directory_entry_id');
    }

    public function systems(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingSystem::class);
    }

    public function equipmentAssignments(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingAsset::class);
    }

    public function statusLabel(): string
    {
        return [
            'pending_it' => 'รอ IT เปิดระบบ',
            'in_progress' => 'IT กำลังดำเนินการ',
            'it_completed' => 'รอ HR อนุมัติแสดงรายชื่อ',
            'hr_approved' => 'อนุมัติและแสดงรายชื่อแล้ว',
            'cancel_requested' => 'รอ IT ตรวจสอบการยกเลิก',
            'cancelled' => 'ยกเลิก',
        ][$this->status] ?? $this->status;
    }

    public function hasItStarted(): bool
    {
        if (in_array($this->status, ['in_progress', 'it_completed'], true) || $this->claimed_by_id) {
            return true;
        }

        if (! $this->relationLoaded('systems')) {
            return $this->systems()->where(function ($query): void {
                $query->where('status', '!=', 'pending')
                    ->orWhereNotNull('email')
                    ->orWhereNotNull('it_asset_id')
                    ->orWhere(function ($usernameQuery): void {
                        $usernameQuery->where('system_name', '!=', 'WDC Portal')
                            ->whereNotNull('username');
                    });
            })->exists();
        }

        return $this->systems->contains(function (EmployeeOnboardingSystem $system): bool {
            return $system->status !== 'pending'
                || filled($system->email)
                || filled($system->it_asset_id)
                || ($system->system_name !== 'WDC Portal' && filled($system->username));
        });
    }

    public function displayName(): string
    {
        return $this->english_nickname
            ? "{$this->english_name} ({$this->english_nickname})"
            : $this->english_name;
    }
}
