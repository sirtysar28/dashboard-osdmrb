<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterType extends Model
{
    protected $fillable = [
        'code', 'name', 'code_format', 'template_body', 'needs_verification', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'needs_verification' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function letters()
    {
        return $this->hasMany(Letter::class);
    }
}
