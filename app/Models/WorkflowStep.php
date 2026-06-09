<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_template_id',
        'step_order',
        'name',
        'mode',
        'approver_group',
        'approver_hint',
        'condition_label',
        'requires_input',
    ];

    protected function casts(): array
    {
        return ['requires_input' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class, 'current_step_id');
    }
}
