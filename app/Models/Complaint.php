<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_LABELS = [
        'submitted' => 'รับเรื่อง',
        'reviewing' => 'ตรวจสอบ',
        'resolved' => 'แก้ไขแล้ว',
        'closed' => 'ปิดเรื่อง',
    ];

    // Keep legacy values readable while current writes use STATUS_LABELS.
    public const PENDING_STATUSES = ['submitted', 'reviewing', 'in_review', 'pending'];

    protected $fillable = [
        'reporter_id',
        'assigned_to',
        'type',
        'status',
        'subject',
        'details',
        'is_anonymous',
        'submitted_to',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public static function pendingStatuses(): array
    {
        return self::PENDING_STATUSES;
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
