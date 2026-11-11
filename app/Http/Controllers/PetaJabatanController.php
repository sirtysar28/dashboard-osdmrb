<?php

namespace App\Http\Controllers;

use App\Exports\GenericExport;
use App\Imports\PositionMapsImport;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PositionMap;
use App\Models\Unit;
use App\Services\JabatanSyncService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dashboard Peta Jabatan (Catatan Masukan 21 Okt 2026, diperbarui 23 Okt &
 * 7 Nov 2026 — Point 4: kolom KERTAS KERJA lengkap).
 *
 * Halaman memadukan dua sumber data:
 *  1. KERTAS KERJA (tabel position_maps, diisi via import Excel — format
 *     "KERTAS KERJA EXCEL.xlsx"): Jabatan dan Jenjang, Dukungan Lintas
 *     Program, Intervensi Program 5 T, Kualifikasi Pendidikan, Kompetensi,
 *     Kebutuhan, Alternatif Penataan/Pemenuhan, dan Rekomendasi Utama.
 *  2. DATA PEGAWAI AKTIF: "Pemangku Jabatan Eksisting" dihitung langsung
 *     dari data pegawai (selalu mutakhir) & "Kesenjangan Jumlah" =
 *     eksisting − kebutuhan. Angka snapshot pada berkas hanya dipakai
 *     sebagai fallback bila jenis jabatan belum ada di data pegawai.
 */
class PetaJabatanController extends Controller
{
    /**
     * Halaman utama: statistik, grafik, tabel kertas kerja peta jabatan
     * (11 kolom) & tabel sebaran per unit kerja, dengan filter unit kerja
     * (eselon I / tingkat balai) & jabatan.
     */
    public function index(Request $request)
    {
        $filters = [
            'es1' => $request->input('es1'),
            'balai' => $request->input('balai'),
            'jabatan' => $request->input('jabatan'),
        ];

        // pegawai fungsional aktif sesuai filter unit → dasar seluruh angka hidup
        $employees = $this->fungsionalEmployees($filters);

        $unitTable = $this->buildUnitRows($employees);
        $jabatanList = $unitTable->pluck('name')->unique()->sort()->values();

        if ($jabatan = $filters['jabatan'] ?? null) {
            $unitTable = $unitTable->filter(fn ($r) => mb_strtolower($r->name) === mb_strtolower(trim((string) $jabatan)))->values();
        }

        // ---- KERTAS KERJA: baris import + angka hidup dari data pegawai ----
        $kertasKerja = $this->buildKertasKerjaRows($employees, $filters['jabatan'] ?? null);

        $jenisCount = $jabatanList->count();

        // statistik ringkasan
        $summary = [
            'existing' => (int) $kertasKerja->sum('existing'),
            'required' => (int) $kertasKerja->sum('required'),
            'shortage' => (int) $kertasKerja->filter(fn ($r) => $r->gap !== null && $r->gap < 0)->sum(fn ($r) => abs($r->gap)),
            'rows' => $kertasKerja->count(),
        ];

        // tabel unit: jumlah pegawai terbanyak dulu, nama A-Z sebagai pemecah seri
        $sorted = $unitTable->sort(fn ($a, $b) => [$b->existing_count, $a->name] <=> [$a->existing_count, $b->name])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $table = new LengthAwarePaginator(
            $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // grafik: 12 jenis jabatan dengan jumlah pegawai terbanyak (eksisting vs kebutuhan)
        $requiredByJenis = PositionMap::all()
            ->groupBy(fn ($r) => mb_strtolower($r->jenis()))
            ->map(fn ($group) => (int) $group->sum('required_count'));

        $chart = $sorted->take(12)->map(fn ($r) => [
            'label' => $r->name,
            'existing' => $r->existing_count,
            'required' => (int) ($requiredByJenis[mb_strtolower($r->name)] ?? 0),
        ])->values();

        return view('modules.peta-jabatan', [
            'summary' => $summary,
            'kertasKerja' => $kertasKerja,
            'table' => $table,
            'chart' => $chart,
            'filters' => $filters,
            'filterOptions' => [
                'es1List' => Unit::where('level', 'ES_I')->ordered()->get(),
                'balaiList' => Unit::where('level', 'BALAI')->ordered()->get(),
                'jabatanList' => $jabatanList,
            ],
            'hasKertasKerja' => PositionMap::exists(),
        ]);
    }

    /**
     * Import kertas kerja peta jabatan dari Excel (ganti seluruh data).
     * Admin Bagian, Biro SDM & Super Admin.
     */
    public function importStore(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new PositionMapsImport();

        try {
            $count = $import->import(
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Berkas tidak dapat diproses. '.$e->getMessage());
        }

        return redirect()->route('modules.peta-jabatan')
            ->with('success', "Import kertas kerja peta jabatan selesai — {$count} baris dimuat. ".
                'Kolom "Pemangku Jabatan Eksisting" & "Kesenjangan Jumlah" tetap dihitung langsung dari data pegawai.');
    }

    /**
     * Ubah satu baris kertas kerja peta jabatan — KHUSUS Admin Bagian
     * & Super Admin (role "admin" saja; Biro SDM/pegawai hanya lihat).
     * Kolom "Pemangku Jabatan Eksisting" & "Kesenjangan Jumlah" tidak
     * diubah karena selalu dihitung otomatis dari data pegawai aktif.
     */
    public function update(Request $request, PositionMap $position_map)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'cross_program' => ['nullable', 'string'],
            'program_intervention' => ['nullable', 'string'],
            'education_qualification' => ['nullable', 'string'],
            'competency' => ['nullable', 'string'],
            'required_count' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'fulfillment_alternative' => ['nullable', 'string'],
            'main_recommendation' => ['nullable', 'string'],
        ]);

        $position_map->update($data);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'peta-jabatan',
            'Ubah baris kertas kerja peta jabatan: '.$position_map->name);

        return redirect()->route('modules.peta-jabatan')
            ->with('success', 'Baris kertas kerja "'.$position_map->name.'" berhasil diperbarui.');
    }

    /**
     * Hapus satu baris kertas kerja peta jabatan — KHUSUS Admin Bagian
     * & Super Admin (role "admin" saja; Biro SDM/pegawai hanya lihat).
     */
    public function destroy(PositionMap $position_map)
    {
        $name = $position_map->name;
        $position_map->delete();

        AuditLog::record(AuditLog::EVENT_DELETE, 'peta-jabatan',
            'Hapus baris kertas kerja peta jabatan: '.$name);

        return redirect()->route('modules.peta-jabatan')
            ->with('success', 'Baris kertas kerja "'.$name.'" berhasil dihapus.');
    }

    /**
     * Unduh template / data terakhir kertas kerja peta jabatan (format Excel).
     */
    public function template()
    {
        $headings = [
            'No', 'Jabatan dan Jenjang', 'Dukungan Lintas Program', 'Intervensi Program 5 T',
            'Kualifikasi Pendidikan', 'Kompetensi', 'Kebutuhan', 'Pemangku jabatan eksisiting',
            'Kesenjangan Jumlah', 'Alternatif Penataan/Pemenuhan', 'Rekomendasi Utama',
        ];

        $rows = PositionMap::orderBy('sort_order')->get();

        $export = $rows->isNotEmpty()
            ? $rows->values()->map(fn ($r, $i) => [
                'No' => $i + 1,
                'Jabatan dan Jenjang' => $r->name,
                'Dukungan Lintas Program' => $r->cross_program,
                'Intervensi Program 5 T' => $r->program_intervention,
                'Kualifikasi Pendidikan' => $r->education_qualification,
                'Kompetensi' => $r->competency,
                'Kebutuhan' => $r->required_count,
                'Pemangku jabatan eksisiting' => $r->existing_count,
                'Kesenjangan Jumlah' => $r->gap_count,
                'Alternatif Penataan/Pemenuhan' => $r->fulfillment_alternative,
                'Rekomendasi Utama' => $r->main_recommendation,
            ])
            : collect([[
                'No' => 1,
                'Jabatan dan Jenjang' => 'Analis Kepegawaian Ahli Muda',
                'Dukungan Lintas Program' => 'Penataan organisasi, Pengelolaan SDM',
                'Intervensi Program 5 T' => 'Trans Gotong Royong',
                'Kualifikasi Pendidikan' => "Berstatus PNS\nBerijazah paling rendah sarjana/diploma empat bidang ...",
                'Kompetensi' => 'Mampu mengelola dan mengembangkan proses penyelenggaraan manajemen ASN ...',
                'Kebutuhan' => 5,
                'Pemangku jabatan eksisiting' => 3,
                'Kesenjangan Jumlah' => -2,
                'Alternatif Penataan/Pemenuhan' => "1. perpindahan jabatan melalui uji kompetensi\n2. pengembangan kompetensi pegawai",
                'Rekomendasi Utama' => "1. pengembangan kompetensi internal\n2. penerimaan mutasi",
            ]]);

        return Excel::download(
            new GenericExport($export, $headings, 'Kertas Kerja Peta Jabatan'),
            'template-kertas-kerja-peta-jabatan.xlsx',
        );
    }

    /**
     * Export kertas kerja peta jabatan (mengikuti filter aktif) ke Excel —
     * 11 kolom lengkap termasuk angka hidup eksisting & kesenjangan.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['es1', 'balai', 'jabatan']);
        $rows = $this->buildKertasKerjaRows($this->fungsionalEmployees($filters), $filters['jabatan'] ?? null);

        $export = $rows->values()->map(fn ($r, $i) => [
            'No' => $i + 1,
            'Jabatan dan Jenjang' => $r->name,
            'Dukungan Lintas Program' => $r->cross_program,
            'Intervensi Program 5 T' => $r->program_intervention,
            'Kualifikasi Pendidikan' => $r->education_qualification,
            'Kompetensi' => $r->competency,
            'Kebutuhan' => $r->required ?? 0,
            'Pemangku Jabatan Eksisting' => $r->existing,
            'Kesenjangan Jumlah' => $r->gap,
            'Alternatif Penataan/Pemenuhan' => $r->fulfillment_alternative,
            'Rekomendasi Utama' => $r->main_recommendation,
        ]);

        if ($export->isEmpty()) {
            return back()->with('error', 'Tidak ada data peta jabatan untuk diekspor.');
        }

        AuditLog::record(AuditLog::EVENT_UPDATE, 'peta-jabatan', 'Ekspor kertas kerja peta jabatan ('.$export->count().' baris)');

        return Excel::download(
            new GenericExport($export, array_keys($export->first()), 'Kertas Kerja Peta Jabatan'),
            'peta-jabatan-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /* ================= HELPERS ================= */

    /**
     * Kumpulan pegawai ASN aktif berjabatan FUNGSIONAL TERTENTU sesuai
     * filter unit kerja — diurai menjadi [jenis, jenjang, unit] untuk
     * kebutuhan tabel sebaran & pencocokan kertas kerja.
     *
     * @return Collection<int, object{jenis: string, jenjang: ?string, unit_name: string}>
     */
    private function fungsionalEmployees(array $filters): Collection
    {
        $query = Employee::query()
            ->with('unit')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('employee_type')
                ->orWhere('employee_type', '!=', Employee::TYPE_NON_ASN))
            ->whereNotNull('position_name')
            ->where('position_name', '!=', '');

        if ($es1 = $filters['es1'] ?? null) {
            $query->whereIn('unit_id', $this->unitDescendants(collect([$es1])));
        }

        if ($balai = $filters['balai'] ?? null) {
            $query->whereIn('unit_id', $this->unitDescendants(collect([$balai])));
        }

        $rows = collect();

        foreach ($query->get(['id', 'unit_id', 'position_name', 'eselon', 'functional_level']) as $employee) {
            // jabatan struktural (bereselon) bukan cakupan peta jabatan fungsional
            if (JabatanSyncService::romanEselon($employee->eselon) !== null) {
                continue;
            }

            if (! JabatanSyncService::looksFungsional($employee->position_name, $employee->functional_level)) {
                continue;
            }

            [$jenis, $jenjang] = PositionMap::splitJenjang($employee->position_name);
            $jenjang = $jenjang ?: (trim((string) $employee->functional_level) ?: null);

            $rows->push((object) [
                'jenis' => $jenis,
                'jenjang' => $jenjang,
                'unit_name' => $employee->unit?->name ?? 'Tanpa Unit Kerja',
            ]);
        }

        return $rows;
    }

    /**
     * Tabel sebaran per (jenis jabatan, unit kerja) — detail pendukung.
     *
     * @param  Collection<int, object>  $employees
     * @return Collection<int, object>
     */
    private function buildUnitRows(Collection $employees): Collection
    {
        $rows = collect();

        foreach ($employees as $employee) {
            $key = mb_strtolower($employee->jenis).'|'.mb_strtolower($employee->unit_name);

            $row = $rows->get($key);

            if (! $row) {
                $row = (object) [
                    'name' => $employee->jenis,
                    'unit_name' => $employee->unit_name,
                    'existing_count' => 0,
                    'levels' => [],
                ];
                $rows->put($key, $row);
            }

            $row->existing_count++;

            if ($employee->jenjang) {
                $row->levels[$employee->jenjang] = ($row->levels[$employee->jenjang] ?? 0) + 1;
            }
        }

        return $rows->values()->each(function ($row) {
            ksort($row->levels);
            $row->levels_string = $row->levels === []
                ? '-'
                : implode(', ', array_map(fn ($c, $l) => $l.' ('.$c.')', $row->levels, array_keys($row->levels)));
        });
    }

    /**
     * Bangun baris KERTAS KERJA PETA JABATAN (11 kolom):
     * baris hasil import dipadukan angka hidup dari data pegawai —
     * "Pemangku Jabatan Eksisting" & "Kesenjangan Jumlah" selalu dihitung
     * dari data pegawai aktif (sesuai filter); snapshot berkas dipakai bila
     * jenis jabatan belum terpetakan di data pegawai. Kombinasi jenis+jenjang
     * yang ada pemangkunya namun belum ada di kertas kerja ditampilkan
     * dengan penanda "belum dipetakan" agar tidak ada data hilang.
     *
     * @param  Collection<int, object>  $employees
     * @return Collection<int, object>
     */
    private function buildKertasKerjaRows(Collection $employees, ?string $jabatanFilter): Collection
    {
        // jenis (lower) => [jenjang (lower, '' bila tanpa jenjang) => jumlah]
        $live = [];

        foreach ($employees as $employee) {
            $jenisKey = mb_strtolower($employee->jenis);
            $jenjangKey = mb_strtolower((string) $employee->jenjang);
            $live[$jenisKey][$jenjangKey] = ($live[$jenisKey][$jenjangKey] ?? 0) + 1;
        }

        $rows = collect();
        $matched = []; // jenis|jenjang (lower) yang telah terwakili kertas kerja

        foreach (PositionMap::orderBy('sort_order')->orderBy('id')->get() as $map) {
            [$jenis, $jenjang] = PositionMap::splitJenjang($map->name);
            $jenisKey = mb_strtolower($jenis);
            $jenjangKey = mb_strtolower((string) $jenjang);

            if ($jabatanFilter !== null && $jenisKey !== mb_strtolower(trim((string) $jabatanFilter))) {
                continue;
            }

            $matched[$jenisKey.'|'.$jenjangKey] = true;

            // jenis jabatan dikenali di data pegawai → angka hidup; bila tidak → snapshot berkas
            $hasLive = array_key_exists($jenisKey, $live);
            $existing = $hasLive ? ($live[$jenisKey][$jenjangKey] ?? 0) : $map->existing_count;
            $gap = $existing - $map->required_count;

            $rows->push((object) [
                'id' => $map->id,
                'name' => $map->name,
                'jenis' => $jenis,
                'jenjang' => $jenjang,
                'cross_program' => $map->cross_program,
                'program_intervention' => $map->program_intervention,
                'education_qualification' => $map->education_qualification,
                'competency' => $map->competency,
                'required' => $map->required_count,
                'existing' => $existing,
                'existing_from_snapshot' => ! $hasLive,
                'gap' => $gap,
                'fulfillment_alternative' => $map->fulfillment_alternative,
                'main_recommendation' => $map->main_recommendation,
                'outside' => false,
            ]);
        }

        // kombinasi jenis+jenjang yang ADA pemangkunya tetapi belum di kertas kerja
        foreach ($live as $jenisKey => $levels) {
            foreach ($levels as $jenjangKey => $count) {
                if (isset($matched[$jenisKey.'|'.$jenjangKey])) {
                    continue;
                }

                if ($jabatanFilter !== null && $jenisKey !== mb_strtolower(trim((string) $jabatanFilter))) {
                    continue;
                }

                $sample = $employees->first(fn ($e) => mb_strtolower($e->jenis) === $jenisKey);

                $rows->push((object) [
                    'id' => null, // kombinasi jenis+jenjang di luar kertas kerja — bukan baris DB
                    'name' => $jenjangKey !== '' ? $sample->jenis.' '.mb_convert_case($jenjangKey, MB_CASE_TITLE, 'UTF-8') : $sample->jenis,
                    'jenis' => $sample->jenis,
                    'jenjang' => $jenjangKey !== '' ? mb_convert_case($jenjangKey, MB_CASE_TITLE, 'UTF-8') : null,
                    'cross_program' => null,
                    'program_intervention' => null,
                    'education_qualification' => null,
                    'competency' => null,
                    'required' => null,
                    'existing' => $count,
                    'existing_from_snapshot' => false,
                    'gap' => null,
                    'fulfillment_alternative' => null,
                    'main_recommendation' => null,
                    'outside' => true,
                ]);
            }
        }

        return $rows->values();
    }

    /**
     * ID unit terpilih beserta seluruh turunannya
     * (resep sama dengan DashboardService::unitDescendants).
     *
     * @param  Collection<int, mixed>  $unitIds
     * @return array<int, int>
     */
    private function unitDescendants(Collection $unitIds): array
    {
        $all = $unitIds->map(fn ($v) => (int) $v)->unique()->values();
        $parents = $all->values();

        while ($parents->isNotEmpty()) {
            $parents = Unit::whereIn('parent_id', $parents)->pluck('id');
            $all = $all->merge($parents)->unique()->values();
        }

        return $all->all();
    }
}
