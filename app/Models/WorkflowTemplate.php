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
        return collect($this->schemaFieldDefinitions())
            ->map(fn (array|string $field) => is_array($field) ? ($field['label'] ?? $field['key'] ?? '') : $field)
            ->filter()
            ->values()
            ->all();
    }

    public function schemaFieldDefinitions(): array
    {
        return collect($this->form_schema['fields'] ?? [])
            ->map(function (array|string $field) {
                if (is_array($field)) {
                    return [
                        'key' => $field['key'] ?? str($field['label'] ?? 'field')->slug('_')->toString(),
                        'label' => $field['label'] ?? $field['key'] ?? '',
                        'type' => $field['type'] ?? 'text',
                        'required' => (bool) ($field['required'] ?? false),
                        'help' => $field['help'] ?? null,
                        'options' => $field['options'] ?? [],
                    ];
                }

                return [
                    'key' => str($field)->slug('_')->toString(),
                    'label' => $field,
                    'type' => 'text',
                    'required' => false,
                    'help' => null,
                    'options' => [],
                ];
            })
            ->filter(fn (array $field) => $field['label'] !== '')
            ->values()
            ->all();
    }

    public function routingRules(): array
    {
        return $this->form_schema['routing'] ?? [];
    }

    public function statusFlow(): array
    {
        return $this->form_schema['statuses'] ?? [];
    }

    public function sourceNotes(): array
    {
        return $this->form_schema['source_notes'] ?? [];
    }
}
