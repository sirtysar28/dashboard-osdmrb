<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PositionType extends Model
{
    protected $fillable = ['code', 'name'];

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
