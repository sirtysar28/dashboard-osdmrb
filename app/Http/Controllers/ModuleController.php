<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\Sop;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Controller modul-menu baru Biro OSDMRB:
 * - Analisis Jabatan Fungsional
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
       ========================================================= */
    public function analisisJabatan(): View
    {
        // Jabatan fungsional tertentu + jumlah pemangku jabatan aktif
        $positions = Position::whereHas('positionType', fn ($q) => $q->where('code', 'FUNGSIONAL'))
            ->with(['jobLevel', 'positionType'])
            ->withCount(['employeePositions as holders_count' => fn ($q) => $q->where('is_current', true)])
            ->orderBy('code')
            ->get();

        // Distribusi pemangku per jenjang fungsional (dari data pegawai ASN aktif)
        $jenjang = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereNotNull('functional_level')
            ->selectRaw('functional_level as label, count(*) as total')
            ->groupBy('functional_level')
            ->orderByDesc('total')
            ->get();

        $totalJabatan = $positions->count();
        $totalPemangku = $positions->sum('holders_count');
        $jabatanKosong = $positions->where('holders_count', 0)->count();

        return view('modules.analisis-jabatan', compact(
            'positions', 'jenjang', 'totalJabatan', 'totalPemangku', 'jabatanKosong'
        ));
    }

    /* =========================================================
       1b. ANALISIS JABATAN STRUKTURAL
       ========================================================= */
    public function analisisJabatanStruktural(): View
    {
        // Jabatan struktural (JPT & pejabat administratif) + jumlah pemangku aktif
        $positions = Position::whereHas('positionType', fn ($q) => $q->where('code', 'STRUKTURAL'))
            ->with(['jobLevel', 'positionType'])
            ->withCount(['employeePositions as holders_count' => fn ($q) => $q->where('is_current', true)])
            ->orderBy('code')
            ->get();

        // Distribusi pejabat struktural per eselon (dari data pegawai ASN aktif)
        $eselonDist = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereIn('eselon', ['II', 'III', 'IV'])
            ->selectRaw("eselon as label, count(*) as total")
            ->groupBy('eselon')
            ->orderBy('eselon')
            ->get()
            ->map(fn ($row) => [
                'label' => match ($row->label) {
                    'II' => 'Eselon II (JPT Pratama / Administrator tinggi)',
                    'III' => 'Eselon III (Administrator)',
                    'IV' => 'Eselon IV (Pengawas)',
                    default => 'Eselon '.$row->label,
                },
                'total' => (int) $row->total,
            ]);

        // Sebaran pejabat struktural per unit kerja eselon II
        $perUnit = Employee::query()
            ->where('employees.is_active', true)
            ->where('employees.employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereIn('employees.eselon', ['II', 'III', 'IV'])
            ->join('units', 'units.id', '=', 'employees.unit_id')
            ->selectRaw('units.name as label, count(*) as total')
            ->groupBy('units.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total]);

        $totalPejabat = $eselonDist->sum('total');
        $totalJabatan = $positions->count();
        $totalPemangku = $positions->sum('holders_count');
        $jabatanKosong = $positions->where('holders_count', 0)->count();

        return view('modules.analisis-jabatan-struktural', compact(
            'positions', 'eselonDist', 'perUnit', 'totalPejabat', 'totalJabatan', 'totalPemangku', 'jabatanKosong'
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
     * Simpan riwayat diklat pegawai baru (admin & biro SDM).
     */
    public function diklatStore(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'name' => ['required', 'max:255'],
            'type' => ['required', Rule::in(array_keys(EmployeeTraining::TYPES))],
            'organizer' => ['nullable', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'certificate_number' => ['nullable', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // sertifikat maks 10 MB
        ], [
            'employee_id.required' => 'Pegawai peserta diklat wajib dipilih.',
            'name.required' => 'Nama diklat wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai harus setelah tanggal mulai.',
        ]);

        $validated['file_path'] = $request->file('file')?->store('diklat', 'public');
        $validated['file_name'] = $request->file('file')?->getClientOriginalName();
        $validated['uploaded_by'] = $request->user()->id;

        EmployeeTraining::create($validated);

        return redirect()->route('modules.diklat')
            ->with('success', 'Riwayat diklat pegawai berhasil ditambahkan.');
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
     * Hapus riwayat diklat (admin & biro SDM).
     */
    public function diklatDestroy(EmployeeTraining $training)
    {
        if ($training->file_path) {
            Storage::disk('public')->delete($training->file_path);
        }

        $training->delete();

        return redirect()->route('modules.diklat')
            ->with('success', 'Riwayat diklat berhasil dihapus.');
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
            'title.required' => 'Nama SOP wajib diisi.',
            'category.required' => 'Kategori SOP wajib dipilih.',
            'category.in' => 'Kategori SOP tidak valid.',
        ]);

        $validated['file_path'] = $request->file('file')?->store('sops', 'public');
        $validated['file_name'] = $request->file('file')?->getClientOriginalName();
        $validated['uploaded_by'] = $request->user()->id;

        $sop = Sop::create($validated);

        \App\Models\AuditLog::record(\App\Models\AuditLog::EVENT_CREATE, 'sop', 'Mengunggah dokumen SOP: '.$sop->title);

        // notifikasi email pengajuan dokumen SOP ke Administrator Utama
        \App\Services\Notifier::notifyAdmins(
            type: 'sop',
            title: 'Pengajuan Dokumen SOP',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada dokumen SOP baru yang diunggah ke aplikasi Dashboard Biro OSDMRB dan menunggu peninjauan.',
            ],
            fields: [
                'Judul SOP' => $sop->title,
                'Kategori' => $sop->category,
                'Nomor' => $sop->number ?: '-',
                'Diunggah oleh' => $request->user()->name,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('modules.sop'),
            actionText: 'Lihat Daftar SOP',
        );

        return redirect()->route('modules.sop')
            ->with('success', 'SOP berhasil diunggah.');
    }

    /**
     * Unduh dokumen SOP.
     */
    public function sopDownload(Sop $sop)
    {
        abort_unless($sop->file_path && Storage::disk('public')->exists($sop->file_path),
            404, 'Dokumen SOP tidak tersedia.');

        return Storage::disk('public')->download($sop->file_path, $sop->file_name ?: basename($sop->file_path));
    }

    /**
     * Preview dokumen SOP — ditampilkan langsung di popup (inline di browser).
     */
    public function sopPreview(Sop $sop)
    {
        abort_unless($sop->file_path && Storage::disk('public')->exists($sop->file_path),
            404, 'Dokumen SOP tidak tersedia.');

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
            ->with('success', 'SOP berhasil dihapus.');
    }

    /* =========================================================
       6. STRUKTUR ORGANISASI
       ========================================================= */
    public function strukturOrganisasi(): View
    {
        $units = Unit::where('is_active', true)->orderBy('name')->get();

        // Susun tree: KEMENTERIAN -> ES_I -> ES_II/ES_III -> BALAI/LAINNYA
        $tree = $this->buildUnitTree($units);

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
