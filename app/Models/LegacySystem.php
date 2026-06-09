<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacySystem extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'category',
        'url',
        'login_method',
        'summary',
        'status',
        'sort_order',
        'is_featured',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ExternalSystemAccount::class);
    }
}
