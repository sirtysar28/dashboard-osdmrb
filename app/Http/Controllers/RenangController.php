<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * DATA RENANG PEGAWAI — Catatan Masukan 19 Nov 2026.
 *
 * Menu baru (grup "Analisis Kepegawaian") yang memetakan kemampuan
 * berenang pegawai ASN (kolom swimming_skill: lulus / belum / belum diisi).
 *
 * Halaman memuat:
 * - Filter pencarian: Unit Kerja & Kemampuan Berenang (tombol Cari & Reset)
 * - Card ringkasan: Total Pegawai, Lulus Berenang, Belum Lulus
 * - Statistik: Rekapitulasi kemampuan renang per Unit Kerja
 * - Daftar Data Renang Pegawai (tabel + pagination)
 */
class RenangController extends Controller
{
    public function index(Request $request): View
    {
        $unitId = $request->input('unit');
        $skill = $request->input('kemampuan');

        // basis data: PEGAWAI ASN aktif saja
        $base = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->with('unit:id,name');

        if ($unitId) {
            $base->where('unit_id', $unitId);
        }

        if ($skill === 'lulus' || $skill === 'belum') {
            $base->where('swimming_skill', $skill);
        } elseif ($skill === 'kosong') {
            $base->whereNull('swimming_skill');
        }

        /* ---------- Card ringkasan (mengikuti filter) ---------- */
        $statsQuery = clone $base;

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'lulus' => (clone $statsQuery)->where('swimming_skill', 'lulus')->count(),
            'belum' => (clone $statsQuery)->where('swimming_skill', 'belum')->count(),
            'kosong' => (clone $statsQuery)->whereNull('swimming_skill')->count(),
        ];

        /* ---------- Rekapitulasi kemampuan renang per unit kerja ---------- */
        $rekap = (clone $base)
            ->get(['id', 'unit_id', 'swimming_skill'])
            ->groupBy(fn ($e) => $e->unit?->name ?? 'Tanpa Unit Kerja')
            ->map(function ($rows, $unitName) {
                return (object) [
                    'unit' => $unitName,
                    'total' => $rows->count(),
                    'lulus' => $rows->where('swimming_skill', 'lulus')->count(),
                    'belum' => $rows->where('swimming_skill', 'belum')->count(),
                    'kosong' => $rows->filter(fn ($r) => $r->swimming_skill === null)->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        /* ---------- Daftar data renang pegawai ---------- */
        $employees = $base
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('renang.index', [
            'employees' => $employees,
            'stats' => $stats,
            'rekap' => $rekap,
            'filters' => ['unit' => $unitId, 'kemampuan' => $skill],
            'unitOptions' => Unit::ordered()->get(),
        ]);
    }
}
