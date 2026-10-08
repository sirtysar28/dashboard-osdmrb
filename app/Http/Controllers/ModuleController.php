<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmployeeTraining;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\Sop;
use App\Models\Unit;
use App\Services\JabatanSyncService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controller modul-menu baru Biro OSDMRB:
 * - Analisis Jabatan Fungsional
 * - Analisis Jabatan Struktural
 * - Analisis Jabatan Pelaksana
 * - Reformasi Birokrasi
 * - Manajemen Talenta
 * - Diklat & Pengembangan Kompetensi
 * - SOP Kementerian
 * - Struktur Organisasi
 */
class ModuleController extends Controller
{
    /* =========================================================
       1. ANALISIS JABATAN FUNGSIONAL
       Catatan Masukan 30 Sept 2026:
       - jabatan yang kosong (tanpa pemangku) tidak ditampilkan;
       - kolom Nomor paling awal pada tabel;
       - filter nama jabatan & jenjang + pagination.
       ========================================================= */
    public function analisisJabatan(Request $request): View
    {
        // sinkronkan nama jabatan dari data pegawai agar SEMUA jenis
        // jabatan fungsional teridentifikasi (catatan 28 Sept 2026)
        $this->syncJabatan();

        // Jabatan fungsional tertentu + pemangku dihitung dari NAMA JABATAN
        // pada data pegawai aktif (bukan hanya relasi riwayat jabatan).
        $all = $this->buildPositionAnalysis('FUNGSIONAL');

        // jabatan kosong TIDAK ditampilkan (butir 8 Catatan 30 Sept 2026)
        $positions = $this->filterPositions($all, $request)
            ->filter(fn ($p) => $p->holders_count > 0)
            ->values();

        // Distribusi pemangku per jenjang fungsional (dari data pegawai ASN aktif)
        $jenjang = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereNotNull('functional_level')
            ->selectRaw('functional_level as label, count(*) as total')
            ->groupBy('functional_level')
            ->orderByDesc('total')
            ->get();

        $totalJabatan = $all->count();
        $totalPemangku = $all->sum('holders_count');
        $jabatanKosong = $all->where('holders_count', 0)->count();

        $positions = $this->paginateCollection($positions, 15, $request);

        $jenjangOptions = $this->jenjangOptions(['AHLI_PERTAMA', 'AHLI_MUDA', 'AHLI_MADYA', 'AHLI_UTAMA', 'PENYELIA', 'TERAMPIL']);

        return view('modules.analisis-jabatan', compact(
            'positions', 'jenjang', 'totalJabatan', 'totalPemangku', 'jabatanKosong', 'jenjangOptions'
        ));
    }

    /* =========================================================
       1b. ANALISIS JABATAN STRUKTURAL
       Catatan Masukan 30 Sept 2026: memakai NAMA JABATAN UMUM
       (Kepala Biro, Direktur, dst.) — bukan nama jabatan lengkap —
       + kolom Nomor, filter nama jabatan & jenjang, dan pagination.
       ========================================================= */
    public function analisisJabatanStruktural(Request $request): View
    {
        // sinkronkan agar tiap jabatan struktural menampilkan pimpinannya
        $this->syncJabatan();

        $all = $this->buildPositionAnalysis('STRUKTURAL', generic: true);

        $positions = $this->paginateCollection(
            $this->filterPositions($all, $request)->values(), 15, $request
        );

        // Distribusi pejabat struktural per eselon (dari data pegawai ASN aktif)
        $eselonDist = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereIn('eselon', ['I', 'II', 'III', 'IV'])
            ->selectRaw("eselon as label, count(*) as total")
            ->groupBy('eselon')
            ->orderBy('eselon')
            ->get()
            ->map(fn ($row) => [
                'label' => match ($row->label) {
                    'I' => 'Eselon I (Sekjen, Ditjen, Itjen)',
                    'II' => 'Eselon II (Direktur, Kabiro, Kapus, dll.)',
                    'III' => 'Eselon III (Kabag, Kabalai)',
                    'IV' => 'Eselon IV (Kasubag)',
                    default => 'Eselon '.$row->label,
                },
                'total' => (int) $row->total,
            ]);

        // Sebaran pejabat struktural per unit kerja eselon II
        $perUnit = Employee::query()
            ->where('employees.is_active', true)
            ->where('employees.employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereIn('employees.eselon', ['I', 'II', 'III', 'IV'])
            ->join('units', 'units.id', '=', 'employees.unit_id')
            ->selectRaw('units.name as label, count(*) as total')
            ->groupBy('units.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total]);

        $totalPejabat = $eselonDist->sum('total');
        $totalJabatan = $all->count();
        $totalPemangku = $all->sum('holders_count');
        $jabatanKosong = $all->where('holders_count', 0)->count();

        $jenjangOptions = $this->jenjangOptions(['ESELON_I', 'ESELON_II', 'ESELON_III', 'ESELON_IV']);

        return view('modules.analisis-jabatan-struktural', compact(
            'positions', 'eselonDist', 'perUnit', 'totalPejabat', 'totalJabatan', 'totalPemangku', 'jabatanKosong', 'jenjangOptions'
        ));
    }

    /* =========================================================
       HELPER ANALISIS JABATAN (28 Sept 2026 + Catatan 30 Sept 2026)
       ========================================================= */

    /** Jalankan sinkronisasi master jabatan dari data pegawai (idempoten). */
    private function syncJabatan(): void
    {
        try {
            JabatanSyncService::syncFromEmployees();
        } catch (\Throwable) {
            // analisis tetap ditampilkan walau sinkronisasi bermasalah
        }
    }

    /** Opsi filter jenjang (job level) berdasarkan kode. */
    private function jenjangOptions(array $codes)
    {
        return JobLevel::whereIn('code', $codes)->orderBy('sort_order')->pluck('name', 'code');
    }

    /** Filter koleksi jabatan berdasarkan NAMA JABATAN & JENJANG (butir 8–10 Catatan 30 Sept 2026). */
    private function filterPositions($positions, Request $request)
    {
        $nama = trim((string) $request->input('nama'));
        $jenjang = trim((string) $request->input('jenjang'));

        return $positions
            ->when($nama !== '', fn ($c) => $c->filter(
                fn ($p) => str_contains(mb_strtolower($p->name), mb_strtolower($nama))
            ))
            ->when($jenjang !== '', fn ($c) => $c->filter(
                fn ($p) => ($p->jobLevel?->code ?? null) === $jenjang
            ))
            ->values();
    }

    /** Paginate koleksi (tabel jabatan — Catatan 30 Sept 2026). */
    private function paginateCollection($items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->input('page', 1));
        $items = $items->values();

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * Bangun daftar jabatan (master + data pegawai) beserta pemangkunya.
     *
     * Pemangku dihitung dari NAMA JABATAN pada data pegawai aktif — bukan hanya
     * dari relasi riwayat jabatan — sehingga semua jenis jabatan yang sudah
     * masuk di data pegawai teridentifikasi dan tiap jabatan struktural
     * menampilkan pimpinannya (catatan 28 Sept 2026).
     *
     * Catatan 30 Sept 2026: $generic = true mengelompokkan pemangku berdasar
     * NAMA JABATAN UMUM ("Kepala Biro ..." → "Kepala Biro") untuk halaman
     * analisis jabatan struktural.
     *
     * @return \Illuminate\Support\Collection<int, Position>
     */
    private function buildPositionAnalysis(string $typeCode, bool $generic = false)
    {
        $master = Position::whereHas('positionType', fn ($q) => $q->where('code', $typeCode))
            ->with('jobLevel')
            ->get();

        // mode nama umum: master hasil sinkronisasi 28 Sept yang memakai nama
        // jabatan LENGKAP (mis. "Kepala Biro Organisasi...") dilipat ke bentuk
        // umumnya — hanya master bernama umum yang ditampilkan sebagai baris
        if ($generic) {
            $master = $master->filter(
                fn ($p) => JabatanSyncService::normalize(JabatanSyncService::genericJabatan($p->name))
                    === JabatanSyncService::normalize($p->name)
            );
        }

        // pegawai ASN aktif sesuai jenis jabatan yang dianalisis
        $employees = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereNotNull('position_name')
            ->where('position_name', '!=', '')
            ->get(['id', 'name', 'position_name', 'eselon', 'functional_level'])
            ->filter(function (Employee $e) use ($typeCode) {
                $struktural = JabatanSyncService::romanEselon($e->eselon) !== null;

                return $typeCode === 'STRUKTURAL'
                    ? $struktural
                    : (! $struktural && JabatanSyncService::looksFungsional($e->position_name, $e->functional_level));
            });

        // kelompokkan berdasarkan nama jabatan (normal: upper + spasi rapat);
        // mode generic memakai nama jabatan umum (butir 9 Catatan 30 Sept 2026)
        $groups = $employees->groupBy(fn ($e) => JabatanSyncService::normalize(
            $generic ? JabatanSyncService::genericJabatan($e->position_name) : $e->position_name
        ));

        // pemangku lewat riwayat jabatan aktif yang tidak tertangkap nama jabatan
        $linked = EmployeePosition::query()
            ->where('is_current', true)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)
                ->where('employee_type', '!=', Employee::TYPE_NON_ASN))
            ->whereHas('position', fn ($q) => $q->whereHas('positionType', fn ($t) => $t->where('code', $typeCode)))
            ->with(['employee:id,name', 'position:id,name'])
            ->get();

        $attachHolders = function (Position $position) use ($groups, $linked, $generic) {
            $key = JabatanSyncService::normalize($position->name);

            $names = $groups->get($key)?->pluck('name') ?? collect();

            $extra = $linked
                ->filter(fn ($lp) => JabatanSyncService::normalize(
                    $generic ? JabatanSyncService::genericJabatan($lp->position?->name) : $lp->position?->name
                ) === $key
                    && ! $names->contains($lp->employee?->name))
                ->map(fn ($lp) => $lp->employee?->name)
                ->filter();

            $holders = $names->merge($extra)->unique()->values();

            $position->holders_count = $holders->count();
            $position->holders_list = $holders->take(3)->implode(', ');
            $position->holders_more = max(0, $holders->count() - 3);

            return $position;
        };

        $positions = $master->map($attachHolders);

        // jabatan pada data pegawai yang belum ada di master (cadangan bila
        // sinkronisasi belum berjalan) — dibuat sebagai model tanpa disimpan
        $masterKeys = $master->mapWithKeys(fn ($p) => [JabatanSyncService::normalize($p->name) => true]);

        foreach ($groups->keys()->diff($masterKeys->keys()) as $key) {
            $sample = $groups->get($key)->first();

            $position = new Position([
                'code' => 'DATA',
                'name' => $generic
                    ? JabatanSyncService::genericJabatan($sample->position_name)
                    : trim((string) $sample->position_name),
            ]);

            $level = JobLevel::where(
                'code',
                JabatanSyncService::jobLevelCode($sample->eselon, $sample->functional_level, $sample->position_name)
            )->first();

            $position->job_level_id = $level?->id;
            $position->setRelation('jobLevel', $level);

            $positions->push($attachHolders($position));
        }

        // jabatan terisi di atas, kosong di bawah — masing-masing urut nama
        return $positions->sortBy([
            ['holders_count', 'desc'],
            ['name', 'asc'],
        ])->values();
    }

    /* =========================================================
       1c. ANALISIS JABATAN PELAKSANA
       Catatan Masukan 30 Sept 2026: data sebelumnya kosong karena dihitung
       dari relasi riwayat jabatan — kini pemangku dihitung langsung dari
       DATA PEGAWAI (jabatan pelaksana / fungsional umum) + kolom Nomor,
       filter nama jabatan & jenjang, dan pagination.
       ========================================================= */
    public function analisisJabatanPelaksana(Request $request): View
    {
        // pegawai ASN aktif berjabatan PELAKSANA / fungsional umum:
        // bukan pejabat struktural (tanpa eselon) & bukan fungsional tertentu
        $pelaksana = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereNotNull('position_name')
            ->where('position_name', '!=', '')
            ->with('unit')
            ->get(['id', 'name', 'position_name', 'eselon', 'functional_level', 'unit_id'])
            ->filter(fn (Employee $e) => JabatanSyncService::romanEselon($e->eselon) === null
                && ! JabatanSyncService::looksFungsional($e->position_name, $e->functional_level));

        $master = Position::whereHas('positionType', fn ($q) => $q->where('code', 'PELAKSANA'))
            ->with(['jobLevel', 'positionType'])
            ->get();

        $groups = $pelaksana->groupBy(fn ($e) => JabatanSyncService::normalize($e->position_name));

        $attachHolders = function (Position $position) use ($groups) {
            $holders = $groups->get(JabatanSyncService::normalize($position->name))?->pluck('name') ?? collect();

            $holders = $holders->unique()->values();

            $position->holders_count = $holders->count();
            $position->holders_list = $holders->take(3)->implode(', ');
            $position->holders_more = max(0, $holders->count() - 3);

            return $position;
        };

        $all = $master->map($attachHolders)->values();

        // jabatan pelaksana pada data pegawai yang belum ada di master
        // — dibuat sebagai model tanpa disimpan (agar data tetap tampil)
        $masterKeys = $master->mapWithKeys(fn ($p) => [JabatanSyncService::normalize($p->name) => true]);

        foreach ($groups->keys()->diff($masterKeys->keys()) as $key) {
            $sample = $groups->get($key)->first();

            $position = new Position([
                'code' => 'DATA',
                'name' => trim((string) $sample->position_name),
            ]);

            $levelCode = JabatanSyncService::jobLevelCode($sample->eselon, $sample->functional_level, $sample->position_name)
                ?? 'PELAKSANA';
            $level = JobLevel::where('code', $levelCode)->first();

            $position->job_level_id = $level?->id;
            $position->setRelation('jobLevel', $level);

            $all->push($attachHolders($position));
        }

        $all = $all->sortBy([
            ['holders_count', 'desc'],
            ['name', 'asc'],
        ])->values();

        $positions = $this->paginateCollection(
            $this->filterPositions($all, $request)->values(), 15, $request
        );

        // Distribusi pemangku per jenjang (dari data pegawai, bukan relasi)
        $levelNames = JobLevel::query()->pluck('name', 'code');

        $jenjang = $pelaksana
            ->groupBy(fn ($e) => $levelNames[
                JabatanSyncService::jobLevelCode($e->eselon, $e->functional_level, $e->position_name) ?? 'PELAKSANA'
            ] ?? 'Pelaksana (Non-Eselon & Non-Fungsional)')
            ->map(fn ($group, $label) => ['label' => $label, 'total' => $group->count()])
            ->sortByDesc('total')
            ->values();

        // Sebaran pelaksana per unit kerja (dari data pegawai)
        $perUnit = $pelaksana
            ->filter(fn ($e) => $e->unit_id !== null)
            ->groupBy(fn ($e) => $e->unit?->name ?? '-')
            ->map(fn ($group, $label) => ['label' => $label, 'total' => $group->count()])
            ->sortByDesc('total')
            ->take(12)
            ->values();

        $totalJabatan = $all->count();
        $totalPemangku = $all->sum('holders_count');
        $jabatanKosong = $all->where('holders_count', 0)->count();
        $totalPelaksana = $pelaksana->count();

        $jenjangOptions = $this->jenjangOptions(['PELAKSANA', 'PENYELIA', 'TERAMPIL']);

        return view('modules.analisis-jabatan-pelaksana', compact(
            'positions', 'jenjang', 'perUnit', 'totalJabatan', 'totalPemangku', 'jabatanKosong', 'totalPelaksana', 'jenjangOptions'
        ));
    }

    /* =========================================================
       2. REFORMASI BIROKRASI
       ========================================================= */
    public function reformasiBirokrasi(): View
    {
        // 8 area Reformasi Birokrasi (sesuai Grand Design RB)
        $areas = [
            ['icon' => 'bi-clipboard2-check', 'name' => 'Simplifikasi Birokrasi', 'desc' => 'Rasionalisasi struktur, proses bisnis, dan peraturan yang menghambat pelayanan.', 'progress' => 65, 'status' => 'Berjalan'],
            ['icon' => 'bi-diagram-3', 'name' => 'Penataan Kelembagaan', 'desc' => 'Evaluasi dan penataan organisasi sesuai beban kerja dan hasil analisis jabatan.', 'progress' => 50, 'status' => 'Berjalan'],
            ['icon' => 'bi-person-check', 'name' => 'Sistem Merit', 'desc' => 'Manajemen talenta dan pengisian jabatan berbasis kinerja, transparan, dan kompetitif.', 'progress' => 40, 'status' => 'Berjalan'],
            ['icon' => 'bi-graph-up-arrow', 'name' => 'Akuntabilitas Kinerja', 'desc' => 'Penerapan manajemen kinerja berbasis hasil (outcome) dan data.', 'progress' => 55, 'status' => 'Berjalan'],
            ['icon' => 'bi-shield-check', 'name' => 'Pengawasan Reformasi', 'desc' => 'Penguatan pengawasan internal dan tindak lanjut hasil pengawasan.', 'progress' => 45, 'status' => 'Berjalan'],
            ['icon' => 'bi-lightbulb', 'name' => 'Perubahan Pola Pikir & Budaya Kerja', 'desc' => 'Internalisasi nilai dasar dan budaya kerja ber AKHLAK (Berorientasi Pelayanan, Akuntabel, Kompeten, Harmonis, Loyal, Adaptif, Kolaboratif).', 'progress' => 70, 'status' => 'Berjalan'],
            ['icon' => 'bi-file-earmark-text', 'name' => 'Manajemen SDM Aparatur', 'desc' => 'Modernisasi sistem informasi kepegawaian terintegrasi (MyASN).', 'progress' => 35, 'status' => 'Berjalan'],
            ['icon' => 'bi-people', 'name' => 'Penataan Pengelolaan JF & JFT', 'desc' => 'Penyusunan formasi, needs analysis, dan evaluasi jabatan fungsional tertentu.', 'progress' => 30, 'status' => 'Dimulai'],
        ];

        return view('modules.reformasi-birokrasi', compact('areas'));
    }

    /* =========================================================
       3. MANAJEMEN TALENTA
       ========================================================= */
    public function manajemenTalenta(): View
    {
        $actives = Employee::where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN) // hanya ASN (konsisten dgn dashboard & daftar pegawai)
            ->with('rank', 'employmentStatus', 'unit')
            ->get();

        // Segmentasi sederhana (heuristik) — siap dikembangkan jadi 9-box grid bila data kinerja tersedia
        $talents = $actives->map(function ($e) {
            $age = $e->age;
            $isSeniorJabatan = $e->eselon !== null
                || in_array($e->functional_level, ['Ahli Utama', 'Utama', 'Ahli Madya', 'Madya']);

            return [
                'employee' => $e,
                'age' => $age,
                'category' => match (true) {
                    $age !== null && $age <= 40 && $isSeniorJabatan => 'High Potential',
                    $age !== null && $age <= 40 => 'Kader Muda',
                    $isSeniorJabatan => 'Siap Suksesi',
                    $age !== null && $e->retirement_date && $e->retirement_date->diffInYears(now()) <= 2 => 'Persiapan Pensiun',
                    default => 'Reguler',
                },
            ];
        });

        $summary = [
            'total' => $actives->count(),
            'highPotential' => $talents->where('category', 'High Potential')->count(),
            'kaderMuda' => $talents->where('category', 'Kader Muda')->count(),
            'siapSuksesi' => $talents->where('category', 'Siap Suksesi')->count(),
            'persiapanPensiun' => $talents->where('category', 'Persiapan Pensiun')->count(),
        ];

        $order = ['High Potential' => 0, 'Siap Suksesi' => 1, 'Kader Muda' => 2];
        $pipeline = $talents
            ->sortBy(fn ($t) => $order[$t['category']] ?? 9)
            ->values()
            ->take(25);

        return view('modules.manajemen-talenta', compact('summary', 'pipeline'));
    }

    /* =========================================================
       4. DIKLAT & PENGEMBANGAN KOMPETENSI
       ========================================================= */
    public function diklat(Request $request): View
    {
        $jenis = [
            ['icon' => 'bi-award', 'code' => 'KEPEMIMPINAN', 'name' => 'Diklat Kepemimpinan', 'desc' => 'Pelatihan Kepemimpinan Tingkat Tinggi (PKTT), Administratif (PKA), dan Pengawasan (PKP) bagi pejabat struktural/pimpinan tinggi.'],
            ['icon' => 'bi-tools', 'code' => 'TEKNIS', 'name' => 'Diklat Teknis', 'desc' => 'Pelatihan teknis sesuai tugas dan fungsi jabatan (kepegawaian, kearsipan, keuangan, dsb).'],
            ['icon' => 'bi-person-workspace', 'code' => 'FUNGSIONAL', 'name' => 'Diklat Fungsional', 'desc' => 'Pelatihan fungsional bagi pejabat fungsional tertentu (Analis SDM, Auditor, Arsiparis, Pranata Komputer).'],
            ['icon' => 'bi-chat-heart', 'code' => 'SOSIAL_KULTURAL', 'name' => 'Diklat Sosial Kultural', 'desc' => 'Pelatihan pengembangan kompetensi sosial kultural dan budaya kerja aparatur.'],
        ];

        $trainings = EmployeeTraining::query()
            ->with(['employee' => fn ($q) => $q->select('id', 'name', 'nip'), 'uploader'])
            ->when($request->filled('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('organizer', 'like', "%{$v}%")
                ->orWhereHas('employee', fn ($e) => $e
                    ->where('name', 'like', "%{$v}%")
                    ->orWhere('nip', 'like', "%{$v}%"))))
            ->when($request->filled('jenis'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->filled('tahun'), fn ($q, $v) => $q->where('year', $v))
            ->orderByDesc('year')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $employees = Employee::where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'nip']);

        $years = EmployeeTraining::select('year')->distinct()->whereNotNull('year')->orderByDesc('year')->pluck('year');

        return view('modules.diklat', compact('jenis', 'trainings', 'employees', 'years'));
    }

    /**
     * Simpan riwayat diklat/seminar/pelatihan pegawai baru.
     * Admin & Biro SDM dapat menginput untuk pegawai mana pun;
     * pegawai dapat menambahkan sendiri pada profilnya (update profil).
     */
    public function diklatStore(Request $request)
    {
        $user = $request->user();

        // pegawai biasa hanya boleh untuk dirinya sendiri (dicek SEBELUM
        // validasi agar akses ilegal selalu 403 walau isian belum lengkap)
        $targetEmployeeId = (int) $request->input('employee_id');
        abort_unless($user->isPrivileged() || $targetEmployeeId === (int) $user->employee_id,
            403, 'Anda hanya dapat menambahkan riwayat untuk profil Anda sendiri.');

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(array_keys(EmployeeTraining::TYPES))],
            'scope' => ['nullable', Rule::in(array_keys(EmployeeTraining::scopeOptions()))],
            'organizer' => ['nullable', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours' => ['nullable', 'integer', 'min' => 0, 'max' => 9999],
            'certificate_number' => ['nullable', 'max:100'],
            // Catatan 30 Sept 2026: unggah dokumen/sertifikat bukti keikutsertaan WAJIB
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // sertifikat maks 10 MB
        ], [
            'employee_id.required' => 'Pegawai peserta wajib dipilih.',
            'name.required' => 'Nama diklat/seminar/pelatihan wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai harus setelah tanggal mulai.',
            'file.required' => 'Dokumen/sertifikat bukti keikutsertaan diklat/seminar/pelatihan wajib diunggah (PDF/JPG, maks. 10 MB).',
        ]);

        $validated['scope'] = $validated['scope'] ?? EmployeeTraining::SCOPE_DOMESTIC;
        $validated['file_path'] = $request->file('file')?->store('diklat', 'public');
        $validated['file_name'] = $request->file('file')?->getClientOriginalName();
        $validated['uploaded_by'] = $user->id;

        EmployeeTraining::create($validated);

        // kembali ke halaman asal (modul diklat atau profil pegawai)
        $redirect = $request->input('from') === 'profile'
            ? back()
            : redirect()->route('modules.diklat');

        return $redirect->with('success', 'Riwayat diklat/seminar/pelatihan berhasil ditambahkan.');
    }

    /**
     * Unduh salinan sertifikat diklat.
     */
    public function diklatDownload(EmployeeTraining $training)
    {
        abort_unless($training->file_path && Storage::disk('public')->exists($training->file_path),
            404, 'Sertifikat diklat tidak tersedia.');

        return Storage::disk('public')->download($training->file_path, $training->file_name ?: basename($training->file_path));
    }

    /**
     * Hapus riwayat diklat (admin & biro SDM, atau pegawai pemilik riwayat).
     */
    public function diklatDestroy(Request $request, EmployeeTraining $training)
    {
        $user = $request->user();

        abort_unless($user->isPrivileged() || $training->employee_id === $user->employee_id,
            403, 'Anda tidak memiliki akses untuk menghapus riwayat ini.');

        if ($training->file_path) {
            Storage::disk('public')->delete($training->file_path);
        }

        $training->delete();

        return back()->with('success', 'Riwayat diklat berhasil dihapus.');
    }

    /* =========================================================
       5. SOP KEMENTERIAN
       ========================================================= */
    public function sop(Request $request): View
    {
        $kategori = Sop::CATEGORIES;

        $sops = Sop::query()
            ->with('uploader')
            ->when($request->filled('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")
                ->orWhere('number', 'like', "%{$v}%")
                ->orWhere('unit', 'like', "%{$v}%")))
            ->when($request->filled('kategori'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->filled('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('year')
            ->orderBy('title')
            ->paginate(10)
            ->withQueryString();

        // jumlah dokumen per kategori (untuk kartu ringkasan)
        $counts = Sop::selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return view('modules.sop', compact('kategori', 'sops', 'counts'));
    }

    /**
     * Simpan dokumen SOP baru (admin & biro SDM).
     */
    public function sopStore(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'max:255'],
            'category' => ['required', Rule::in(Sop::CATEGORIES)],
            'number' => ['nullable', 'max:100'],
            'unit' => ['nullable', 'max:150'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'status' => ['required', Rule::in(array_keys(Sop::STATUSES))],
            'description' => ['nullable'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'], // dokumen maks 10 MB
        ], [
            'title.required' => 'Nama dokumen wajib diisi.',
            'category.required' => 'Kategori dokumen wajib dipilih.',
            'category.in' => 'Kategori dokumen tidak valid.',
        ]);

        $validated['file_path'] = $request->file('file')?->store('sops', 'public');
        $validated['file_name'] = $request->file('file')?->getClientOriginalName();
        $validated['uploaded_by'] = $request->user()->id;

        $sop = Sop::create($validated);

        \App\Models\AuditLog::record(\App\Models\AuditLog::EVENT_CREATE, 'sop', 'Mengunggah dokumen kepegawaian: '.$sop->title);

        // notifikasi email pengajuan dokumen SOP ke Administrator Utama
        \App\Services\Notifier::notifyAdmins(
            type: 'sop',
            title: 'Pengajuan Dokumen Kepegawaian',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada dokumen kepegawaian baru yang diunggah ke aplikasi Dashboard Biro OSDMRB dan menunggu peninjauan.',
            ],
            fields: [
                'Judul Dokumen' => $sop->title,
                'Kategori' => $sop->category,
                'Nomor' => $sop->number ?: '-',
                'Diunggah oleh' => $request->user()->name,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('modules.sop'),
            actionText: 'Lihat Daftar Dokumen',
        );

        return redirect()->route('modules.sop')
            ->with('success', 'Dokumen berhasil diunggah.');
    }

    /**
     * Unduh dokumen SOP.
     */
    public function sopDownload(Sop $sop)
    {
        abort_unless($sop->file_path && Storage::disk('public')->exists($sop->file_path),
            404, 'Dokumen tidak tersedia.');

        return Storage::disk('public')->download($sop->file_path, $sop->file_name ?: basename($sop->file_path));
    }

    /**
     * Preview dokumen SOP — ditampilkan langsung di popup (inline di browser).
     */
    public function sopPreview(Sop $sop)
    {
        abort_unless($sop->file_path && Storage::disk('public')->exists($sop->file_path),
            404, 'Dokumen tidak tersedia.');

        $name = $sop->file_name ?: basename($sop->file_path);
        $mime = Storage::disk('public')->mimeType($sop->file_path) ?: 'application/octet-stream';

        return response()->file(Storage::disk('public')->path($sop->file_path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $name).'"',
        ]);
    }

    /**
     * Hapus dokumen SOP (admin & biro SDM).
     */
    public function sopDestroy(Sop $sop)
    {
        if ($sop->file_path) {
            Storage::disk('public')->delete($sop->file_path);
        }

        $sop->delete();

        return redirect()->route('modules.sop')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    /* =========================================================
       6. STRUKTUR ORGANISASI
       ========================================================= */
    public function strukturOrganisasi(): View
    {
        $units = Unit::where('is_active', true)->ordered()->get();

        // Catatan rapat 23 Sept 2026: bagan berakar pada unit KEMENTERIAN yang
        // sebenarnya (bukan root sintetis) agar Eselon I tidak tampil dobel —
        // total Eselon I = 4 (Setjen, Itjen, dan 2 Ditjen).
        $root = $units->firstWhere('level', 'KEMENTERIAN');

        if ($root) {
            $children = $this->buildUnitTree($units->reject(fn ($u) => $u->id === $root->id)->values(), $root->id);
        } else {
            // fallback: belum ada unit KEMENTERIAN — pakai node sintetis
            $root = (object) ['id' => null, 'name' => 'Kementerian Transmigrasi', 'code' => 'ROOT', 'level' => 'KEMENTERIAN'];
            $children = $this->buildUnitTree($units);
        }

        $tree = collect([['unit' => $root, 'children' => $children]]);

        $stats = [
            'es1' => $units->where('level', 'ES_I')->count(),
            'es2' => $units->where('level', 'ES_II')->count(),
            'es3' => $units->where('level', 'ES_III')->count(),
            'balai' => $units->where('level', 'BALAI')->count(),
        ];

        return view('modules.struktur-organisasi', compact('tree', 'stats'));
    }

    /**
     * Bangun pohon unit kerja secara rekursif.
     */
    private function buildUnitTree($units, $parentId = null)
    {
        return $units
            ->filter(fn ($u) => $u->parent_id === $parentId)
            ->map(fn ($u) => [
                'unit' => $u,
                'children' => $this->buildUnitTree($units, $u->id),
            ])
            ->values();
    }
}
