<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRequestAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_request_id',
        'source_system',
        'file_name',
        'file_url',
        'mime_type',
        'uploaded_by',
        'sort_order',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequest::class, 'workflow_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
