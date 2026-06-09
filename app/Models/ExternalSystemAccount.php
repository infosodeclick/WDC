<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSystemAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legacy_system_id',
        'login_identifier',
        'credential_note',
        'notes',
        'last_verified_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'last_verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legacySystem(): BelongsTo
    {
        return $this->belongsTo(LegacySystem::class);
    }
}
