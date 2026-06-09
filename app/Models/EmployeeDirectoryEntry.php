<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDirectoryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'entry_type',
        'display_name',
        'english_name',
        'thai_name',
        'nickname',
        'department',
        'position',
        'location',
        'email',
        'phone',
        'extension_number',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entryTypeLabel(): string
    {
        return [
            'employee' => 'พนักงาน',
            'mail_group' => 'กลุ่มอีเมล',
            'showroom' => 'สาขา/โชว์รูม',
        ][$this->entry_type] ?? $this->entry_type;
    }
}
