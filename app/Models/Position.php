<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'position_type_id', 'job_level_id', 'code', 'name', 'description',
    ];

    public function positionType()
    {
        return $this->belongsTo(PositionType::class);
    }

    public function jobLevel()
    {
        return $this->belongsTo(JobLevel::class);
    }

    public function employeePositions()
    {
        return $this->hasMany(EmployeePosition::class);
    }
}
