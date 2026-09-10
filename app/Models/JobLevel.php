<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobLevel extends Model
{
    protected $fillable = ['code', 'name', 'sort_order'];

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
