<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOnboardingSystem extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'provisioned', 'skipped'];

    protected $fillable = [
        'employee_onboarding_request_id',
        'it_asset_id',
        'system_name',
        'requested_access',
        'username',
        'email',
        'status',
        'notes',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeOnboardingRequest::class, 'employee_onboarding_request_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    public function statusLabel(): string
    {
        return [
            'pending' => 'รอดำเนินการ',
            'provisioned' => 'เปิดแล้ว',
            'skipped' => 'ไม่ต้องเปิด',
        ][$this->status] ?? $this->status;
    }
}
