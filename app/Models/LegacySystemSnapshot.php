<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacySystemSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'snapshot_type',
        'title',
        'source_url',
        'summary',
        'payload',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
