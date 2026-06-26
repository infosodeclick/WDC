<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeOffboardingRequest extends Model
{
    use HasFactory;

    public const STATUSES = ['pending_it', 'in_progress', 'it_completed', 'hr_approved', 'cancelled'];

    protected $fillable = [
        'requested_by',
        'employee_user_id',
        'claimed_by_id',
        'it_completed_by',
        'hr_approved_by',
        'employee_code',
        'employee_name',
        'thai_name',
        'department',
        'position',
        'email',
        'resignation_date',
        'status',
        'hr_note',
        'it_note',
        'claimed_at',
        'it_completed_at',
        'hr_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date',
            'claimed_at' => 'datetime',
            'it_completed_at' => 'datetime',
            'hr_approved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function employeeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    public function itCompleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'it_completed_by');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function systems(): HasMany
    {
        return $this->hasMany(EmployeeOffboardingSystem::class);
    }

    public function displayName(): string
    {
        return "{$this->employee_code} · {$this->employee_name}";
    }

    public function statusLabel(): string
    {
        return [
            'pending_it' => 'รอ IT ปิดระบบ',
            'in_progress' => 'IT กำลังดำเนินการ',
            'it_completed' => 'รอ HR ปิดบัญชี',
            'hr_approved' => 'ปิดบัญชีและย้ายเป็นลาออกแล้ว',
            'cancelled' => 'ยกเลิก',
        ][$this->status] ?? $this->status;
    }
}
