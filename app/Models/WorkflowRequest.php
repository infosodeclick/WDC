<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_template_id',
        'requester_id',
        'current_step_id',
        'assigned_to',
        'document_number',
        'smartflow_menu',
        'title',
        'details',
        'form_payload',
        'assigned_group',
        'priority',
        'status',
        'legacy_reference',
        'due_at',
        'submitted_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'form_payload' => 'array',
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowRequestEvent::class);
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return [
            'low' => 'ต่ำ',
            'normal' => 'ปกติ',
            'high' => 'สูง',
            'critical' => 'วิกฤต',
        ][$this->priority] ?? $this->priority;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'submitted' => 'ส่งคำขอแล้ว',
            'in_review' => 'อยู่ระหว่างตรวจสอบ',
            'accepted' => 'รับเรื่องแล้ว',
            'in_progress' => 'กำลังดำเนินการ',
            'waiting_requester' => 'รอข้อมูลจากผู้ขอ',
            'approved' => 'อนุมัติแล้ว',
            'rejected' => 'ไม่อนุมัติ',
            'completed' => 'เสร็จสิ้น',
            'cancelled' => 'ยกเลิก',
        ];
    }
}
