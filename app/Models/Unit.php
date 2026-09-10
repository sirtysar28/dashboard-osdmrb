<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'parent_id', 'code', 'name', 'level', 'address', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id')->orderBy('name');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            'KEMENTERIAN' => 'Kementerian',
            'ES_I' => 'Eselon I',
            'ES_II' => 'Eselon II',
            'ES_III' => 'Eselon III',
            'BALAI' => 'Balai',
            default => 'Lainnya',
        };
    }
}
