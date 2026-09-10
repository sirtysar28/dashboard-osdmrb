<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EducationLevel extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
