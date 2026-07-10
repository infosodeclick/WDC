<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItAsset extends Model
{
    use HasFactory;

    public const STATUSES = ['stock', 'reserved', 'active', 'repair', 'lost', 'retired'];

    protected $fillable = [
        'asset_category_id',
        'asset_location_id',
        'owner_id',
        'code',
        'name',
        'company',
        'department',
        'owner_name',
        'status',
        'brand',
        'model',
        'serial_number',
        'price',
        'book_value',
        'purchased_at',
        'warranty_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'book_value' => 'decimal:2',
            'purchased_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AssetAuditLog::class);
    }

    public function onboardingAssignments(): HasMany
    {
        return $this->hasMany(EmployeeOnboardingAsset::class);
    }

    public function statusLabel(): string
    {
        return [
            'stock' => 'พร้อมใช้งาน',
            'reserved' => 'จองให้พนักงานใหม่',
            'active' => 'ใช้งานอยู่',
            'repair' => 'ส่งซ่อม',
            'lost' => 'สูญหาย',
            'retired' => 'จำหน่าย/เลิกใช้',
        ][$this->status] ?? $this->status;
    }
}
