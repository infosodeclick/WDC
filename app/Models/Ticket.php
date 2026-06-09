<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    private const REQUEST_TYPE_LABELS = [
        'general' => 'ปัญหา/รายละเอียดทั่วไป',
        'cancel_document' => 'แจ้งยกเลิกเอกสาร',
        'vpn_access' => 'แจ้งขอใช้งาน VPN',
        'sap_b1' => 'แจ้งปัญหาโปรแกรม SAP B1',
        'ai_crm' => 'แจ้งปัญหาโปรแกรม AI-CRM',
        'remote_access' => 'ขอเข้าถึง/แก้ไข database หรือ Remote Access',
    ];

    private const STATUS_LABELS = [
        'open' => 'เปิดงาน',
        'accepted' => 'รับเรื่องแล้ว',
        'in_progress' => 'กำลังดำเนินการ',
        'done' => 'เสร็จสิ้น',
    ];

    private const URGENCY_LABELS = [
        'low' => 'ต่ำ',
        'normal' => 'ปกติ',
        'high' => 'สูง',
        'critical' => 'วิกฤต',
    ];

    protected $fillable = [
        'reporter_id',
        'assigned_to',
        'department_id',
        'title',
        'request_type',
        'details',
        'urgency',
        'status',
        'image_path',
        'legacy_document_ref',
        'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * @return array<string, string>
     */
    public static function requestTypeLabels(): array
    {
        return self::REQUEST_TYPE_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function urgencyLabels(): array
    {
        return self::URGENCY_LABELS;
    }

    public function requestTypeLabel(): string
    {
        return self::REQUEST_TYPE_LABELS[$this->request_type] ?? $this->request_type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function urgencyLabel(): string
    {
        return self::URGENCY_LABELS[$this->urgency] ?? $this->urgency;
    }
}
