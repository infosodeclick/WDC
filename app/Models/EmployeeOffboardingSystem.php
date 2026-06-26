<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOffboardingSystem extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'completed', 'skipped'];

    protected $fillable = [
        'employee_offboarding_request_id',
        'it_asset_id',
        'completed_by_id',
        'system_name',
        'username',
        'email',
        'status',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeOffboardingRequest::class, 'employee_offboarding_request_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function statusLabel(): string
    {
        return [
            'pending' => 'รอดำเนินการ',
            'completed' => 'ดำเนินการแล้ว',
            'skipped' => 'ไม่เกี่ยวข้อง',
        ][$this->status] ?? $this->status;
    }
}
