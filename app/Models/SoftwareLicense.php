<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoftwareLicense extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'expiring', 'expired', 'cancelled'];

    protected $fillable = [
        'code',
        'name',
        'vendor',
        'license_type',
        'seat_count',
        'assigned_seats',
        'cost',
        'department',
        'starts_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'seat_count' => 'integer',
            'assigned_seats' => 'integer',
            'cost' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function statusLabel(): string
    {
        return [
            'active' => 'ใช้งานอยู่',
            'expiring' => 'ใกล้หมดอายุ',
            'expired' => 'หมดอายุ',
            'cancelled' => 'ยกเลิก',
        ][$this->status] ?? $this->status;
    }

    public function availableSeats(): int
    {
        return max(0, $this->seat_count - $this->assigned_seats);
    }
}
