<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentStatus extends Model
{
    protected $fillable = ['code', 'name'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * ID status "ASN/PNS" dan "CPNS" pada master status kepegawaian —
     * dipakai aturan status efektif (ASN + TMT ASN kosong = CPNS).
     */
    public static function asnIds(): array
    {
        return static::query()
            ->get(['id', 'code', 'name'])
            ->filter(fn ($s) => in_array(strtoupper(trim($s->code)), ['ASN', 'PNS'], true)
                || in_array(strtolower(trim($s->name)), ['asn', 'pns'], true))
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    public static function cpnsIds(): array
    {
        return static::query()
            ->get(['id', 'code', 'name'])
            ->filter(fn ($s) => strtoupper(trim($s->code)) === 'CPNS'
                || strtolower(trim($s->name)) === 'cpns')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }
}
