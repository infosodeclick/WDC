<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOnboardingRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending_it', 'in_progress', 'it_completed', 'hr_approved', 'cancelled'];

    protected $fillable = [
        'requested_by',
        'it_completed_by',
        'hr_approved_by',
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
        'it_completed_at',
        'hr_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'it_completed_at' => 'datetime',
            'hr_approved_at' => 'datetime',
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

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
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

    public function statusLabel(): string
    {
        return [
            'pending_it' => 'รอ IT เปิดระบบ',
            'in_progress' => 'IT กำลังดำเนินการ',
            'it_completed' => 'รอ HR อนุมัติแสดงรายชื่อ',
            'hr_approved' => 'อนุมัติและแสดงรายชื่อแล้ว',
            'cancelled' => 'ยกเลิก',
        ][$this->status] ?? $this->status;
    }

    public function displayName(): string
    {
        return $this->english_nickname
            ? "{$this->english_name} ({$this->english_nickname})"
            : $this->english_name;
    }
}
