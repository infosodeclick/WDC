<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

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

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
