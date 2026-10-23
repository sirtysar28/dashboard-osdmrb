<?php

namespace App\Http\Controllers;

use App\Exports\GenericExport;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Unit;
use App\Services\JabatanSyncService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dashboard Peta Jabatan (Catatan Masukan 21 Okt 2026, diperbarui 23 Okt 2026).
 *
 * Memetakan sebaran pegawai ASN aktif pada jabatan fungsional per unit kerja.
 * DATA DIAMBIL LANGSUNG DARI DATA PEGAWAI EKSISTING — bukan import Excel:
 * jumlah penduduk setiap jenis jabatan dihitung otomatis dari nama jabatan
 * & jenjang fungsional pada tabel pegawai, sehingga selalu mutakhir.
 */
class PetaJabatanController extends Controller
{
    /**
     * Jenjang fungsional yang dilepas dari akhir nama jabatan untuk
     * mendapatkan JENIS jabatan (gabungan kata dicek lebih dulu).
     */
    private const JENJANG = [
        'Fungsional Ahli Utama', 'Fungsional Ahli Madya', 'Fungsional Ahli Muda', 'Fungsional Ahli Pertama',
        'Ahli Utama', 'Ahli Madya', 'Ahli Muda', 'Ahli Pertama',
        'Utama', 'Madya', 'Muda', 'Pertama', 'Penyelia', 'Mahir', 'Terampil', 'Pemula',
    ];

    /**
     * Halaman utama: statistik, grafik, dan tabel peta jabatan
     * dengan filter unit kerja (eselon I / tingkat balai) & jabatan.
     */
    public function index(Request $request)
    {
        $filters = [
            'es1' => $request->input('es1'),
            'balai' => $request->input('balai'),
            'jabatan' => $request->input('jabatan'),
        ];

        $rows = $this->buildRows($filters);

        // daftar jenis jabatan (untuk dropdown) — dihitung sebelum filter jabatan
        $jabatanList = $rows->pluck('name')->unique()->sort()->values();

        if ($jabatan = $filters['jabatan'] ?? null) {
            $rows = $rows->filter(fn ($r) => mb_strtolower($r->name) === mb_strtolower(trim((string) $jabatan)))->values();
        }

        $jenisCount = $rows->pluck('name')->unique()->count();

        // statistik ringkasan
        $summary = [
            'existing' => (int) $rows->sum('existing_count'),
            'jenis' => $jenisCount,
            'units' => $rows->pluck('unit_name')->unique()->count(),
            'average' => $jenisCount > 0 ? round($rows->sum('existing_count') / $jenisCount, 1) : 0,
        ];

        // tabel: jumlah pegawai terbanyak dulu, nama A-Z sebagai pemecah seri
        $sorted = $rows->sort(fn ($a, $b) => [$b->existing_count, $a->name] <=> [$a->existing_count, $b->name])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $table = new LengthAwarePaginator(
            $sorted->slice(($page - 1) * $perPage, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        // grafik: 12 jenis jabatan dengan jumlah pegawai terbanyak
        $chart = $sorted->take(12)->map(fn ($r) => [
            'label' => $r->name,
            'existing' => $r->existing_count,
        ])->values();

        return view('modules.peta-jabatan', [
            'summary' => $summary,
            'table' => $table,
            'chart' => $chart,
            'filters' => $filters,
            'filterOptions' => [
                'es1List' => Unit::where('level', 'ES_I')->ordered()->get(),
                'balaiList' => Unit::where('level', 'BALAI')->ordered()->get(),
                'jabatanList' => $jabatanList,
            ],
        ]);
    }

    /**
     * Export data peta jabatan (mengikuti filter aktif) ke Excel.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['es1', 'balai', 'jabatan']);

        $rows = $this->buildRows($filters);

        if ($jabatan = $filters['jabatan'] ?? null) {
            $rows = $rows->filter(fn ($r) => mb_strtolower($r->name) === mb_strtolower(trim((string) $jabatan)));
        }

        $export = $rows
            ->sort(fn ($a, $b) => [$a->name, $a->unit_name] <=> [$b->name, $b->unit_name])
            ->values()
            ->map(fn ($r) => [
                'Jenis Jabatan Fungsional' => $r->name,
                'Unit Kerja' => $r->unit_name,
                'Jenjang Terisi' => $r->levels_string,
                'Jumlah Pegawai' => $r->existing_count,
            ]);

        if ($export->isEmpty()) {
            return back()->with('error', 'Tidak ada data peta jabatan untuk diekspor.');
        }

        AuditLog::record(AuditLog::EVENT_UPDATE, 'peta-jabatan', 'Ekspor data peta jabatan ('.$export->count().' baris)');

        return Excel::download(
            new GenericExport($export, array_keys($export->first()), 'Peta Jabatan'),
            'peta-jabatan-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /* ================= HELPERS ================= */

    /**
     * Bangun baris peta jabatan langsung dari DATA PEGAWAI AKTIF:
     * kelompokkan pegawai ASN berjabatan fungsional per
     * (jenis jabatan, unit kerja) beserta sebaran jennjangnya.
     *
     * @return Collection<int, object>
     */
    private function buildRows(array $filters): Collection
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

            [$jenis, $jenjang] = $this->splitJenjang($employee->position_name);
            $jenjang = $jenjang ?: (trim((string) $employee->functional_level) ?: null);
            $unitName = $employee->unit?->name ?? 'Tanpa Unit Kerja';
            $key = mb_strtolower($jenis).'|'.mb_strtolower($unitName);

            $row = $rows->get($key);

            if (! $row) {
                $row = (object) [
                    'name' => $jenis,
                    'unit_name' => $unitName,
                    'existing_count' => 0,
                    'levels' => [],
                ];
                $rows->put($key, $row);
            }

            $row->existing_count++;

            if ($jenjang) {
                $row->levels[$jenjang] = ($row->levels[$jenjang] ?? 0) + 1;
            }
        }

        // atribut turunan: ringkasan jenjang per baris
        return $rows->values()->each(function ($row) {
            ksort($row->levels);
            $row->levels_string = $row->levels === []
                ? '-'
                : implode(', ', array_map(fn ($c, $l) => $l.' ('.$c.')', $row->levels, array_keys($row->levels)));
        });
    }

    /**
     * Pisahkan nama jabatan lengkap menjadi [JENIS jabatan, JENJANG]:
     * "Analis Kepegawaian Ahli Muda" → ["Analis Kepegawaian", "Ahli Muda"].
     * Nama tanpa jenjang ("Arsiparis") dikembalikan apa adanya.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitJenjang(string $positionName): array
    {
        $jenis = trim((string) preg_replace('/\s+/u', ' ', $positionName));

        foreach (self::JENJANG as $level) {
            $pattern = '/\s+'.preg_quote($level, '/').'$/iu';

            if (preg_match($pattern, $jenis)) {
                $stripped = trim((string) preg_replace($pattern, '', $jenis));

                if ($stripped !== '') {
                    return [$stripped, $level];
                }
            }
        }

        return [$jenis, null];
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
