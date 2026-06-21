<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'it_asset_id',
        'user_id',
        'action',
        'summary',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ItAsset::class, 'it_asset_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
