<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingAsset extends Model
{
    use HasFactory;

    public const STATUSES = ['reserved', 'delivered', 'released'];

    protected $fillable = [
        'employee_onboarding_request_id',
        'it_asset_id',
        'status',
        'assigned_by_id',
        'assigned_at',
        'delivered_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboardingRequest::class, 'employee_onboarding_request_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function statusLabel(): string
    {
        return [
            'reserved' => 'จองเตรียมส่งมอบ',
            'delivered' => 'ส่งมอบแล้ว',
            'released' => 'ยกเลิกการจอง',
        ][$this->status] ?? $this->status;
    }
}
