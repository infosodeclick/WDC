<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_name',
        'title',
        'start_at',
        'end_at',
        'attendees',
        'notes',
        'status',
        'google_event_id',
        'synced_at',
        'sync_error',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'synced_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'synced' => 'ซิงค์แล้ว',
            'sync_failed' => 'ซิงค์ไม่สำเร็จ',
            'cancelled' => 'ยกเลิกแล้ว',
            default => 'กำลังซิงค์',
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'synced' => 'status-done',
            'sync_failed' => 'status-open',
            'cancelled' => 'status-cancelled',
            default => 'status-in_progress',
        };
    }
}
