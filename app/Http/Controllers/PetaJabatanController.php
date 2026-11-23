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
 * Seluruh nama jabatan, eksisting dan kebutuhan utama berasal dari
 * position_maps (hasil import Excel). employees hanya menjadi sumber tabel
 * pendukung sebaran jabatan berdasarkan unit, bukan angka Kertas Kerja.
 */
class PetaJabatanController extends Controller
{
    /**
     * Dashboard Peta Jabatan.
     * Daftar jabatan, eksisting, kebutuhan, dan kesenjangan berasal dari
     * position_maps. employees hanya untuk tabel pendukung sebaran per unit.
     *
     * Catatan: filter unit memengaruhi tabel sebaran pegawai saja, karena
     * position_maps tidak memiliki relasi unit kerja pada implementasi ini.
     */
    public function index(Request $request)
    {
        $filters = [
            'es1' => $request->input('es1'),
            'es2' => $request->input('es2'),
            'balai' => $request->input('balai'),
            'jabatan' => $request->input('jabatan'),
        ];

        // Satu sumber master jabatan, kebutuhan, dan eksisting.
        $positionMaps = PositionMap::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Jenis jabatan untuk dropdown hanya berasal dari position_maps.
        $jabatanList = $positionMaps
            ->map(function ($map) {
                [$jenis] = PositionMap::splitJenjang($map->name);
                return trim((string) $jenis);
            })
            ->filter(fn ($name) => $name !== '')
            ->unique(fn ($name) => $this->normalizeJabatan($name))
            ->sort()
            ->values();

        // Angka ringkasan dan Kertas Kerja menggunakan position_maps saja.
        $kertasKerjaRows = $this->buildKertasKerjaRows($filters['jabatan']);

        $summary = [
            'existing' => (int) $kertasKerjaRows->sum('existing'),
            'required' => (int) $kertasKerjaRows->sum('required'),
            'shortage' => (int) $kertasKerjaRows
                ->filter(fn ($row) => $row->gap < 0)
                ->sum(fn ($row) => abs($row->gap)),
            'gap' => (int) $kertasKerjaRows->sum('gap'),
            'rows' => $kertasKerjaRows->count(),
        ];

        $perPage = 15;
        $kkPage = LengthAwarePaginator::resolveCurrentPage('page');
        $kertasKerja = new LengthAwarePaginator(
            $kertasKerjaRows->slice(($kkPage - 1) * $perPage, $perPage)->values(),
            $kertasKerjaRows->count(),
            $perPage,
            $kkPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'page']
        );

        // Tabel pendukung: keberadaan / sebaran jabatan pada data pegawai.
        // Tidak digunakan dalam penghitungan angka grafik, ringkasan, atau Kertas Kerja.
        $employees = $this->fungsionalEmployees($filters);
        $allowedJenis = $jabatanList
            ->mapWithKeys(fn ($name) => [$this->normalizeJabatan($name) => true]);
        $employees = $employees
            ->filter(fn ($employee) => $allowedJenis->has($this->normalizeJabatan($employee->jenis)))
            ->values();

        $unitTable = $this->buildUnitRows($employees);
        if (! empty($filters['jabatan'])) {
            $selected = $this->normalizeJabatan($filters['jabatan']);
            $unitTable = $unitTable
                ->filter(fn ($row) => $this->normalizeJabatan($row->name) === $selected)
                ->values();
        }

        $sorted = $unitTable
            ->sort(fn ($a, $b) => [$b->existing_count, $a->name] <=> [$a->existing_count, $b->name])
            ->values();

        $unitPage = LengthAwarePaginator::resolveCurrentPage('unit_page');
        $table = new LengthAwarePaginator(
            $sorted->slice(($unitPage - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $unitPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'unit_page']
        );

        // Grafik: hanya jenis jabatan dari position_maps.
        // Setiap jenjang digabung; eksisting dan kebutuhan keduanya SUM dari position_maps.
        $grouped = $positionMaps->groupBy(function ($map) {
            [$jenis] = PositionMap::splitJenjang($map->name);
            return $this->normalizeJabatan($jenis);
        });

        $chart = $grouped
            ->map(function ($group) {
                [$jenis] = PositionMap::splitJenjang($group->first()->name);

                return [
                    'label' => trim((string) $jenis),
                    'existing' => (int) $group->sum('existing_count'),
                    'required' => (int) $group->sum('required_count'),
                    'gap' => (int) $group->sum('existing_count') - (int) $group->sum('required_count'),
                ];
            })
            ->values();

        if (! empty($filters['jabatan'])) {
            $selected = $this->normalizeJabatan($filters['jabatan']);
            $chart = $chart
                ->filter(fn ($row) => $this->normalizeJabatan($row['label']) === $selected)
                ->values();
        }

        $chartHeight = max(450, $chart->count() * 65);

        return view('modules.peta-jabatan', [
            'summary' => $summary,
            'kertasKerja' => $kertasKerja,
            'table' => $table,
            'chart' => $chart,
            'chartHeight' => $chartHeight,
            'filters' => $filters,
            'filterOptions' => [
                'es1List' => Unit::where('level', 'ES_I')->ordered()->get(),
                'es2List' => Unit::where('level', 'ES_II')->ordered()->get(),
                'balaiList' => Unit::where('level', 'BALAI')->ordered()->get(),
                'jabatanList' => $jabatanList,
            ],
            'hasKertasKerja' => $positionMaps->isNotEmpty(),
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
                'Kolom Pemangku Jabatan Eksisting dan Kesenjangan Jumlah bersumber dari position_maps.');
    }

    /**
     * Ubah satu baris kertas kerja peta jabatan — KHUSUS Admin Bagian
     * & Super Admin (role "admin" saja; Biro SDM/pegawai hanya lihat).
     * Kolom "Pemangku Jabatan Eksisting" berasal dari position_maps,
     * sedangkan "Kesenjangan Jumlah" dihitung dari eksisting - kebutuhan.
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
            'existing_count' => ['required', 'integer', 'min:0', 'max:65535'],
            'fulfillment_alternative' => ['nullable', 'string'],
            'main_recommendation' => ['nullable', 'string'],
        ]);

        // existing_count ditulis eksplisit agar tetap tersimpan meskipun
        // belum terdaftar dalam $fillable model PositionMap.
        $position_map->existing_count = (int) $data['existing_count'];
        unset($data['existing_count']);
        $position_map->fill($data);
        $position_map->save();

        // Kesenjangan dihitung ketika data ditampilkan:
        // existing_count - required_count.
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
     * 11 kolom lengkap dengan angka dari position_maps.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['es1', 'es2', 'balai', 'jabatan']);
        $rows = $this->buildKertasKerjaRows($filters['jabatan'] ?? null);

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

        // Catatan Masukan 19 Nov 2026: filter unit kerja eselon II — memuat
        // seluruh turunan unit terpilih (bagian eselon III, dst.)
        if ($es2 = $filters['es2'] ?? null) {
            $query->whereIn('unit_id', $this->unitDescendants(collect([$es2])));
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

        return $rows->values();
    }

    /**
     * Kertas Kerja mengikuti seluruh baris position_maps yang cocok filter.
     * Eksisting dan kebutuhan SELALU dari position_maps, tidak dari employees.
     *
     * @return Collection<int, object>
     */
    private function buildKertasKerjaRows(?string $jabatanFilter): Collection
    {
        $selected = $this->normalizeJabatan($jabatanFilter);
        $rows = collect();

        foreach (PositionMap::orderBy('sort_order')->orderBy('id')->get() as $map) {
            [$jenis, $jenjang] = PositionMap::splitJenjang($map->name);

            // Filter mendukung jenis jabatan atau nama lengkap beserta jenjang.
            if ($selected !== ''
                && $this->normalizeJabatan($jenis) !== $selected
                && $this->normalizeJabatan($map->name) !== $selected) {
                continue;
            }

            $existing = (int) ($map->existing_count ?? 0);
            $required = (int) ($map->required_count ?? 0);

            $rows->push((object) [
                'id' => $map->id,
                'name' => $map->name,
                'jenis' => $jenis,
                'jenjang' => $jenjang,
                'cross_program' => $map->cross_program,
                'program_intervention' => $map->program_intervention,
                'education_qualification' => $map->education_qualification,
                'competency' => $map->competency,
                'required' => $required,
                'existing' => $existing,
                'gap' => $existing - $required,
                'fulfillment_alternative' => $map->fulfillment_alternative,
                'main_recommendation' => $map->main_recommendation,
            ]);
        }

        return $rows->values();
    }

    /** Normalisasi untuk pencocokan jenis dan nama jabatan. */
    private function normalizeJabatan(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
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
