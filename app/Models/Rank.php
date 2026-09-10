<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rank extends Model
{
    protected $fillable = ['code', 'name', 'group_name', 'is_pppk', 'sort_order'];

    protected function casts(): array
    {
        return ['is_pppk' => 'boolean'];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
