<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveCategory extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function archives()
    {
        return $this->hasMany(Archive::class);
    }
}
