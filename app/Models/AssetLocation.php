<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'company',
        'has_gps',
        'latitude',
        'longitude',
        'radius_meters',
    ];

    protected function casts(): array
    {
        return [
            'has_gps' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ItAsset::class);
    }
}
