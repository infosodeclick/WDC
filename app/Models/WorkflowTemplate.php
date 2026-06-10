<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'legacy_workflow_id',
        'name',
        'category',
        'description',
        'smartflow_menu',
        'service_team',
        'form_schema',
        'sla_hours',
        'approval_policy',
        'legacy_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'form_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_template_favorites')->withTimestamps();
    }

    public function schemaFields(): array
    {
        return $this->form_schema['fields'] ?? [];
    }
}
