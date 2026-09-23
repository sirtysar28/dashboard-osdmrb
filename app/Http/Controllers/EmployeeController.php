<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Exports\EmployeesTemplateExport;
use App\Imports\EmployeesImport;
use App\Imports\NonAsnEmployeesImport;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmployeeRankHistory;
use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\Rank;
use App\Models\Unit;
use App\Traits\ExportsTable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    use ExportsTable;

    /**
     * Daftar pegawai ASN (admin).
     * Mendukung filter dari stat-card dashboard: jenis jabatan, pensiun, status, KGB.
     * Filter status & unit mendukung pilihan LEBIH DARI SATU (multi-select).
     */
    public function index(Request $request)
    {
        $employees = $this->filteredQuery($request)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'statusList' => EmploymentStatus::orderBy('name')->get(),
            'unitList' => Unit::orderBy('name')->get(),
            'filters' => $this->normalizeFilters($request),
            'nonAsn' => false,
        ]);
    }

    /**
     * Direktori pegawai — pegawai biasa dapat MELIHAT & MENCARI pegawai lain
     * (hanya view-only, tanpa aksi ubah/hapus).
     */
    public function directory(Request $request)
    {
        $employees = Employee::query()
            ->with(['unit', 'employmentStatus', 'rank', 'education'])
            ->where('is_active', true)
            ->when($request->filled('search'), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")))
            ->when($request->filled('jenis'), function ($q, $v) {
                if ($v === 'asn') {
                    $q->where('employee_type', '!=', Employee::TYPE_NON_ASN);
                } elseif ($v === 'non_asn') {
                    $q->where('employee_type', Employee::TYPE_NON_ASN);
                }
            })
            ->when(collect($request->input('status', []))->filter()->isNotEmpty(),
                fn ($q) => $q->whereIn('employment_status_id', collect($request->input('status'))->filter()))
            ->when(collect($request->input('unit', []))->filter()->isNotEmpty(),
                fn ($q) => $q->whereIn('unit_id', collect($request->input('unit'))->filter()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.directory', [
            'employees' => $employees,
            'statusList' => EmploymentStatus::orderBy('name')->get(),
            'unitList' => Unit::orderBy('name')->get(),
            'filters' => $request->only(['search', 'jenis', 'status', 'unit']),
        ]);
    }

    /**
     * Daftar pegawai NON ASN (pramubakti, security, cleaning service, dll).
     * Filter kategori mendukung pilihan lebih dari satu (multi-select).
     */
    public function nonAsn(Request $request)
    {
        $categories = collect($request->input('category', []))->filter()->values();

        // kompatibilitas tautan lama (?category=Security)
        if ($categories->isEmpty() && is_string($request->input('category')) && $request->input('category') !== '') {
            $categories = collect([$request->input('category')]);
        }

        $employees = Employee::query()
            ->with(['unit', 'employmentStatus', 'education'])
            ->where('employee_type', Employee::TYPE_NON_ASN)
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")))
            ->when($categories->isNotEmpty(), fn ($q) => $q->whereIn('category', $categories))
            ->when($request->has('inactive'), fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.non-asn', [
            'employees' => $employees,
            'categories' => Employee::where('employee_type', Employee::TYPE_NON_ASN)
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only(['search', 'category', 'inactive']),
        ]);
    }

    /**
     * Form tambah pegawai NON ASN — format sederhana (bukan format ASN).
     */
    public function nonAsnCreate()
    {
        return view('employees.non-asn-form', [
            'employee' => new Employee(['is_active' => true]),
            'categories' => $this->nonAsnCategories(),
            'unitList' => Unit::where('level', '>', 1)->orderBy('name')->get(),
        ]);
    }

    /**
     * Simpan pegawai NON ASN baru.
     */
    public function nonAsnStore(Request $request)
    {
        $validated = $this->validatedNonAsn($request);

        $validated['employee_type'] = Employee::TYPE_NON_ASN;

        if (empty($validated['nip'])) {
            $validated['nip'] = NonAsnEmployeesImport::generateCode($validated['category'], $validated['name']);
        }

        $employee = Employee::create($validated);

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai', 'Menambah pegawai non ASN: '.$employee->name);

        return redirect()->route('employees.non-asn')
            ->with('success', "Pegawai non ASN \"{$employee->name}\" berhasil ditambahkan.");
    }

    /**
     * Form ubah pegawai NON ASN.
     */
    public function nonAsnEdit(Employee $employee)
    {
        abort_unless($employee->employee_type === Employee::TYPE_NON_ASN, 404);

        return view('employees.non-asn-form', [
            'employee' => $employee,
            'categories' => $this->nonAsnCategories(),
            'unitList' => Unit::where('level', '>', 1)->orderBy('name')->get(),
        ]);
    }

    /**
     * Simpan perubahan pegawai NON ASN.
     */
    public function nonAsnUpdate(Request $request, Employee $employee)
    {
        abort_unless($employee->employee_type === Employee::TYPE_NON_ASN, 404);

        $validated = $this->validatedNonAsn($request, $employee);

        $employee->update($validated);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pegawai', 'Mengubah pegawai non ASN: '.$employee->name);

        return redirect()->route('employees.non-asn')
            ->with('success', "Data \"{$employee->name}\" berhasil diperbarui.");
    }

    /**
     * Form import pegawai NON ASN dari Excel (3 format berkas instansi didukung).
     */
    public function nonAsnImportForm()
    {
        return view('employees.non-asn-import', [
            'categories' => $this->nonAsnCategories(),
        ]);
    }

    /**
     * Proses import berkas Excel pegawai NON ASN.
     */
    public function nonAsnImportStore(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $import = new NonAsnEmployeesImport($request->input('category'));

        try {
            $result = $import->import(
                $request->file('file')->getRealPath(),
                $request->file('file')->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Berkas tidak dapat dibaca. '.$e->getMessage());
        }

        if ($result['created'] === 0 && $result['updated'] === 0) {
            return back()->with('error', 'Tidak ada baris nama pegawai yang terbaca. Pastikan berkas berisi daftar nama (kolom berjudul "NAMA").');
        }

        return redirect()->route('employees.non-asn')
            ->with('success', "Import kategori {$result['category']} selesai: {$result['created']} pegawai baru, {$result['updated']} diperbarui.");
    }

    /**
     * Daftar kategori non ASN yang pernah terpakai + pilihan standar.
     */
    private function nonAsnCategories(): array
    {
        $existing = Employee::where('employee_type', Employee::TYPE_NON_ASN)
            ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();

        return collect(['Security', 'Cleaning Service', 'Pramubakti'])
            ->merge($existing)->unique()->values()->all();
    }

    /**
     * Validasi input form pegawai NON ASN (kolom sederhana saja).
     */
    private function validatedNonAsn(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'nip')->ignore($employee)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'position_name' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'gender' => ['nullable', 'in:L,P'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:1000'],
            'swimming_skill' => ['nullable', 'in:bisa,tidak'],
            'english_skill' => ['nullable', 'in:'.implode(',', array_keys(Employee::ENGLISH_SKILLS))],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    /**
     * Form tambah pegawai.
     */
    public function create()
    {
        return view('employees.form', [
            'employee' => new Employee(),
            'statusList' => EmploymentStatus::orderBy('name')->get(),
            'rankList' => Rank::orderBy('sort_order')->get(),
            'educationList' => EducationLevel::orderBy('sort_order')->get(),
            'campusList' => Campus::orderBy('sort_order')->orderBy('name')->get(),
            'unitList' => Unit::orderBy('level')->orderBy('name')->get(),
            'positionList' => Position::with('positionType')->orderBy('name')->get(),
            'educationDefaults' => $this->educationFieldDefaults(new Employee(), null),
        ]);
    }

    /**
     * Simpan pegawai baru.
     */
    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated = array_merge($validated, $this->resolvedEducationValues($request));

        $employee = Employee::create($validated);

        if ($request->filled('position_id')) {
            $this->syncPosition($employee, $request);
        }

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai', 'Menambah pegawai: '.$employee->name);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Data pegawai berhasil ditambahkan.');
    }

    /**
     * Profil pegawai yang sedang login (akun pegawai hanya boleh melihat miliknya).
     */
    public function myProfile(Request $request)
    {
        $employee = Employee::find($request->user()->employee_id);

        abort_unless($employee, 404, 'Akun Anda belum terhubung dengan data pegawai. Hubungi admin instansi.');

        $employee->load(['unit', 'education', 'rank', 'employmentStatus',
            'positions.position.positionType', 'positions.position.jobLevel', 'positions.unit',
            'letters.letterType', 'trainings.uploader', 'rankHistories.oldRank', 'rankHistories.newRank']);

        return view('employees.show', [
            'employee' => $employee,
            'isOwnProfile' => true,
            'rankList' => Rank::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Detail pegawai.
     * Pegawai biasa boleh melihat profil pegawai lain (view-only — tombol ubah
     * hanya tampil untuk admin/biro SDM/super admin); data kontak tetap tampil
     * sebagai direktori internal instansi.
     */
    public function show(Employee $employee)
    {
        $employee->load(['unit', 'education', 'rank', 'employmentStatus',
            'positions.position.positionType', 'positions.position.jobLevel', 'positions.unit',
            'letters.letterType', 'trainings.uploader', 'rankHistories.oldRank', 'rankHistories.newRank']);

        return view('employees.show', [
            'employee' => $employee,
            'isOwnProfile' => request()->user()->employee_id === $employee->id,
            'rankList' => Rank::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Unduh / cetak CV pegawai (PDF, format CURRICULUM VITAE siap cetak).
     */
    public function cv(Employee $employee)
    {
        $employee->load(['unit', 'education', 'rank', 'employmentStatus',
            'positions.position', 'positions.unit', 'trainings',
            'rankHistories.oldRank', 'rankHistories.newRank']);

        \App\Services\MasterDataExporter::raiseMemoryLimit('512M');

        return Pdf::loadView('employees.cv', ['employee' => $employee])
            ->setPaper('a4')
            ->download('cv-'.Str::slug($employee->name).'.pdf');
    }

    /**
     * Form ubah pegawai.
     */
    public function edit(Employee $employee)
    {
        $campusList = Campus::orderBy('sort_order')->orderBy('name')->get();

        return view('employees.form', [
            'employee' => $employee->load('currentPosition'),
            'statusList' => EmploymentStatus::orderBy('name')->get(),
            'rankList' => Rank::orderBy('sort_order')->get(),
            'educationList' => EducationLevel::orderBy('sort_order')->get(),
            'campusList' => $campusList,
            'unitList' => Unit::orderBy('level')->orderBy('name')->get(),
            'positionList' => Position::with('positionType')->orderBy('name')->get(),
            'educationDefaults' => $this->educationFieldDefaults($employee, $campusList),
        ]);
    }

    /**
     * Perbarui pegawai.
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validated($request, $employee);
        $validated = array_merge($validated, $this->resolvedEducationValues($request));

        $oldRankId = $employee->rank_id;

        $employee->update($validated);

        // catat otomatis riwayat kenaikan pangkat saat golongan berubah
        $newRankId = array_key_exists('rank_id', $validated) ? $validated['rank_id'] : $oldRankId;

        if ($newRankId !== null && (int) $newRankId !== (int) $oldRankId) {
            $employee->rankHistories()->create([
                'old_rank_id' => $oldRankId,
                'new_rank_id' => $newRankId,
                'effective_date' => $validated['tmt_golongan'] ?? null,
                'notes' => 'Tercatat otomatis dari perubahan data golongan pegawai.',
            ]);
        }

        if ($request->filled('position_id')) {
            $this->syncPosition($employee, $request);
        }

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pegawai', 'Mengubah data pegawai: '.$employee->name);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Data pegawai berhasil diperbarui.');
    }

    /* ================= RIWAYAT KENAIKAN PANGKAT ================= */

    /**
     * Tambah riwayat kenaikan pangkat (mis. III/a -> III/b).
     * Admin bagian / Biro SDM / super admin — atau pegawai untuk profilnya sendiri.
     */
    public function storeRankHistory(Request $request, Employee $employee)
    {
        $this->authorizeProfileEdit($employee);

        $validated = $request->validate([
            'old_rank_id' => ['nullable', 'exists:ranks,id'],
            'new_rank_id' => ['required', 'exists:ranks,id'],
            'sk_number' => ['nullable', 'max:100'],
            'effective_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:255'],
        ], [
            'new_rank_id.required' => 'Golongan/pangkat baru wajib dipilih.',
        ]);

        $history = $employee->rankHistories()->create($validated);

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai',
            'Menambah riwayat kenaikan pangkat: '.$employee->name.' ('.$history->transition_label.')');

        return back()->with('success', 'Riwayat kenaikan pangkat berhasil ditambahkan.');
    }

    /**
     * Hapus riwayat kenaikan pangkat.
     */
    public function destroyRankHistory(Request $request, EmployeeRankHistory $rankHistory)
    {
        $this->authorizeProfileEdit($rankHistory->employee);

        $rankHistory->delete();

        return back()->with('success', 'Riwayat kenaikan pangkat berhasil dihapus.');
    }

    /**
     * Hak menambah/menghapus riwayat pada profil pegawai:
     * admin bagian / Biro SDM / super admin, atau pegawai pemilik profil.
     */
    private function authorizeProfileEdit(Employee $employee): void
    {
        $user = request()->user();

        abort_unless($user->isPrivileged() || $user->employee_id === $employee->id,
            403, 'Anda tidak memiliki akses untuk mengubah data pegawai ini.');
    }

    /**
     * Hapus pegawai.
     */
    public function destroy(Employee $employee)
    {
        AuditLog::record(AuditLog::EVENT_DELETE, 'pegawai', 'Menghapus pegawai: '.$employee->name);

        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Data pegawai berhasil dihapus.');
    }

    /* ================= EXPORT ================= */

    /**
     * Export daftar pegawai ke Excel / PDF (mengikuti filter aktif).
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'xlsx');

        if (strtolower($format) === 'xlsx') {
            return Excel::download(
                new EmployeesExport($this->filteredQuery($request)->orderBy('name')),
                'data-pegawai-'.now()->format('Ymd-His').'.xlsx'
            );
        }

        $rows = $this->filteredQuery($request)
            ->with(['unit', 'education', 'rank', 'employmentStatus'])
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nip,
                $employee->name,
                $employee->gender_label,
                $employee->employmentStatus?->name ?? '-',
                $employee->rank?->code ?? '-',
                $employee->education?->name ?? '-',
                $employee->unit?->name ?? '-',
                $employee->position_name ?? '-',
                $employee->eselon ?: '-',
                $employee->tmt_cpns?->format('d/m/Y') ?? '-',
                $employee->retirement_date?->format('d/m/Y') ?? '-',
                $employee->is_active ? 'Aktif' : 'Non-aktif',
            ]);

        $filterInfo = collect([
            'search' => $request->input('search'),
            'status' => collect($this->normalizeFilters($request)['status'])->implode(','),
            'unit' => collect($this->normalizeFilters($request)['unit'])->implode(','),
        ])->filter()->map(fn ($value, $key) => "{$key}={$value}")->implode(', ');

        return $this->exportTable(
            $format,
            'Data Pegawai',
            ['NIP', 'Nama', 'Jenis Kelamin', 'Status', 'Golongan', 'Pendidikan', 'Unit Kerja', 'Jabatan', 'Eselon', 'TMT CPNS', 'Batas Pensiun', 'Status Aktif'],
            $rows,
            'data-pegawai',
            'Total: '.$rows->count().' pegawai',
            $filterInfo ?: null,
        );
    }

    /* ================= IMPORT MASSAL ================= */

    /**
     * Form import massal pegawai.
     */
    public function importForm()
    {
        return view('employees.import');
    }

    /**
     * Proses import massal pegawai dari Excel/CSV.
     */
    public function importStore(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new EmployeesImport();

        // hitung sel formula (mis. KENAIKAN PANGKAT = EDATE) menjadi nilai murni
        $resolvedPath = \App\Support\ExcelFormulaResolver::resolve($request->file('file')->getRealPath());

        try {
            Excel::import($import, $resolvedPath);
        } catch (\Maatwebsite\Excel\Exceptions\NoFilePathGivenException|\Throwable $e) {
            @unlink($resolvedPath);

            return back()->with('error', 'Berkas tidak dapat dibaca. Pastikan format .xlsx / .xls / .csv. Detail: '.$e->getMessage());
        }

        @unlink($resolvedPath);

        if ($import->created === 0 && $import->updated === 0 && empty($import->errors)) {
            return back()->with('error', 'Tidak ada baris data yang terbaca. Pastikan baris pertama berisi judul kolom sesuai template.');
        }

        $message = 'Import selesai: '.$import->created.' pegawai baru, '.$import->updated.' diperbarui.';

        if ($import->errors) {
            // laporkan maksimal 8 baris bermasalah agar notifikasi tetap ringkas
            $shown = array_slice($import->errors, 0, 8);
            $more = count($import->errors) - count($shown);

            session()->flash('warning', $message.' Baris dilewati: '.count($import->errors).'. '.implode(' | ', $shown).
                ($more > 0 ? " | dan {$more} lainnya..." : ''));

            return redirect()->route('employees.index');
        }

        return redirect()->route('employees.index')->with('success', $message);
    }

    /**
     * Unduh template import pegawai (.xlsx).
     */
    public function template()
    {
        return Excel::download(new EmployeesTemplateExport(), 'template-import-pegawai.xlsx');
    }

    /* ================= HELPERS ================= */

    /**
     * Nilai filter untuk view — status & unit berupa array (multi-select).
     */
    private function normalizeFilters(Request $request): array
    {
        $status = $request->input('status', []);
        $unit = $request->input('unit', []);

        // kompatibilitas tautan lama (string tunggal, mis. ?status=pppk)
        if (is_string($status)) {
            $status = $status === '' ? [] : [$status];
        }
        if (is_string($unit)) {
            $unit = $unit === '' ? [] : [$unit];
        }

        return [
            'search' => $request->input('search'),
            'status' => $status,
            'unit' => $unit,
            'inactive' => $request->input('inactive'),
            'jenis' => $request->input('jenis'),
            'pensiun' => $request->input('pensiun'),
            'naik' => $request->input('naik'),
            'kgb' => $request->input('kgb'),
        ];
    }

    /**
     * Query pegawai dengan filter pencarian/status (bisa >1)/unit (bisa >1)/aktif
     * (dipakai index, export & tautan stat-card dashboard)
     * + filter stat-card dashboard (jenis jabatan, pensiun, kenaikan jabatan, KGB).
     */
    private function filteredQuery(Request $request)
    {
        $statusValues = collect($this->normalizeFilters($request)['status'])->filter()->values();
        $unitValues = collect($this->normalizeFilters($request)['unit'])->filter()->values();

        return Employee::query()
            ->with(['unit', 'education', 'rank', 'employmentStatus'])
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN) // data utama = ASN
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")))
            // status kepegawaian — MULTI-SELECT (mis. ASN + PPPK sekaligus)
            ->when($statusValues->isNotEmpty(), function ($q) use ($statusValues) {
                $statusIds = $statusValues->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v);
                $wantsPppk = $statusValues->contains('pppk');

                $q->where(function ($w) use ($statusIds, $wantsPppk) {
                    if ($statusIds->isNotEmpty()) {
                        $w->whereIn('employment_status_id', $statusIds);
                    }

                    if ($wantsPppk) {
                        $pppk = fn ($e) => $e->where('name', 'like', 'PPPK%');
                        $statusIds->isNotEmpty()
                            ? $w->orWhereHas('employmentStatus', $pppk)
                            : $w->whereHas('employmentStatus', $pppk);
                    }
                });
            })
            // unit kerja — MULTI-SELECT
            ->when($unitValues->isNotEmpty(), fn ($q) => $q->whereIn('unit_id', $unitValues->map(fn ($v) => (int) $v)))
            ->when($request->jenis === 'struktural', fn ($q) => $q->whereNotNull('eselon'))
            ->when($request->jenis === 'fungsional', fn ($q) => $q->whereNotNull('functional_level')
                ->where('functional_level', 'not like', '%Umum%'))
            ->when($request->pensiun, function ($q, $years) {
                if ((int) $years === 1) {
                    // kalender tahun berjalan (sama dgn stat-card "Akan Pensiun Tahun Ini")
                    $q->whereNotNull('retirement_date')->whereYear('retirement_date', now()->year);
                } else {
                    $q->whereNotNull('retirement_date')
                        ->whereBetween('retirement_date', [now(), now()->addYears((int) $years)]);
                }
            })
            /**
             * Filter kenaikan jabatan/pangkat (estimasi = kolom Kenaikan Pangkat/Jabatan
             * bila terisi, jika tidak TMT golongan + 4 tahun — sama dengan dashboard).
             * Opsi: tahun_ini | 1 (≤ 1 tahun) | 4 (≤ 4 tahun) | overdue (jatuh tempo/terlewat).
             * Window TMT digeser -4 tahun agar kueri kompatibel MySQL & SQLite.
             */
            ->when(in_array($request->naik, ['tahun_ini', '1', '4', 'overdue'], true), function ($q) use ($request) {
                if ($request->naik === 'overdue') {
                    // estimasi kenaikan sudah lewat (terlewat/belum diproses)
                    $q->where(fn ($w) => $w
                        ->where('next_promotion_date', '<', now()->startOfDay())
                        ->orWhere(fn ($w2) => $w2
                            ->whereNull('next_promotion_date')
                            ->whereNotNull('tmt_golongan')
                            ->where('tmt_golongan', '<', now()->startOfDay()->subYears(4))));

                    return;
                }

                $range = match ($request->naik) {
                    'tahun_ini' => [now()->startOfYear(), now()->endOfYear()],
                    '1' => [now()->startOfDay(), now()->addYear()],
                    default => [now()->startOfDay(), now()->addYears(4)],
                };

                $q->where(fn ($w) => $w
                    ->whereBetween('next_promotion_date', $range)
                    ->orWhere(fn ($w2) => $w2
                        ->whereNull('next_promotion_date')
                        ->whereNotNull('tmt_golongan')
                        ->whereBetween('tmt_golongan', [
                            $range[0]->copy()->subYears(4),
                            $range[1]->copy()->subYears(4),
                        ])));
            })
            /**
             * Filter Kenaikan Gaji Berkala (KGB) — berkala 2 tahun bagi ASN, CPNS & PPPK.
             * Tanggal KGB = TMT golongan + kelipatan 2 tahun; window TMT digeser
             * -2k tahun (k = 1..25, mencakup masa kerja ± 50 tahun).
             * Opsi: tahun_ini | 1 (≤ 1 tahun) | 2 (≤ 2 tahun) | overdue (jatuh tempo).
             * overdue = TMT golongan sudah > 2 tahun (KGB seharusnya sudah diproses).
             */
            ->when(in_array($request->kgb, ['tahun_ini', '1', '2', 'overdue'], true), function ($q) use ($request) {
                if ($request->kgb === 'overdue') {
                    $q->whereNotNull('tmt_golongan')
                        ->where('tmt_golongan', '<=', now()->subYears(2));

                    return;
                }

                $range = match ($request->kgb) {
                    'tahun_ini' => [now()->startOfYear(), now()->endOfYear()],
                    '1' => [now()->startOfDay(), now()->addYear()],
                    default => [now()->startOfDay(), now()->addYears(2)],
                };

                $q->whereNotNull('tmt_golongan')->where(function ($w) use ($range) {
                    foreach (range(1, 25) as $k) {
                        $w->orWhereBetween('tmt_golongan', [
                            $range[0]->copy()->subYears(2 * $k),
                            $range[1]->copy()->subYears(2 * $k),
                        ]);
                    }
                });
            })
            ->when($request->has('inactive'), fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true));
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'nip' => ['required', 'max:30', Rule::unique('employees', 'nip')->ignore($employee?->id)],
            'name' => ['required', 'max:255'],
            'employee_type' => ['nullable', 'in:asn,non_asn'],
            'category' => ['nullable', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:30'],
            'gender' => ['required', 'in:L,P'],
            'birth_place' => ['nullable', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'religion' => ['nullable', 'max:30'],
            'swimming_skill' => ['nullable', 'in:bisa,tidak'],
            'english_skill' => ['nullable', 'in:'.implode(',', array_keys(Employee::ENGLISH_SKILLS))],
            'address' => ['nullable'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'rank_id' => ['nullable', 'exists:ranks,id'],
            'education_level_id' => ['nullable', 'exists:education_levels,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'eselon' => ['nullable', 'max:10'],
            'functional_level' => ['nullable', 'max:100'],
            'position_name' => ['nullable', 'max:255'],
            'tmt_jabatan' => ['nullable', 'date'],
            'tmt_golongan' => ['nullable', 'date'],
            'next_promotion_date' => ['nullable', 'date'],
            'tmt_cpns' => ['nullable', 'date'],
            'tmt_pns' => ['nullable', 'date'],
            'retirement_date' => ['nullable', 'date'],
            'education_1' => ['nullable', 'max:255'],
            'education_2' => ['nullable', 'max:255'],
            'education_3' => ['nullable', 'max:255'],
            // pendidikan terakhir via dropdown master kampus (S1/S2/S3)
            'education_1_campus' => ['nullable', 'max:20'],
            'education_1_major' => ['nullable', 'max:150'],
            'education_1_custom' => ['nullable', 'max:255'],
            'education_2_campus' => ['nullable', 'max:20'],
            'education_2_major' => ['nullable', 'max:150'],
            'education_2_custom' => ['nullable', 'max:255'],
            'education_3_campus' => ['nullable', 'max:20'],
            'education_3_major' => ['nullable', 'max:150'],
            'education_3_custom' => ['nullable', 'max:255'],
            'npwp' => ['nullable', 'max:40'],
            'karpeg' => ['nullable', 'max:40'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Susun nilai education_1..education_3 dari input dropdown master kampus
     * (atau isian manual) pada form pegawai.
     * Format hasil: "Jurusan, Nama Kampus" atau "Nama Kampus" saja.
     */
    private function resolvedEducationValues(Request $request): array
    {
        $campusList = Campus::orderBy('sort_order')->orderBy('name')->get();

        $values = [];

        foreach ([1, 2, 3] as $i) {
            $values["education_{$i}"] = $this->composeEducationEntry(
                (string) $request->input("education_{$i}_campus"),
                (string) $request->input("education_{$i}_major"),
                (string) $request->input("education_{$i}_custom"),
                $campusList,
            );
        }

        return $values;
    }

    private function composeEducationEntry(string $campus, string $major, string $custom, $campusList): ?string
    {
        $major = trim($major);

        // mode isi manual (kampus tidak ada di master)
        if ($campus === 'custom') {
            $custom = trim($custom);

            return $custom === '' ? null : $custom;
        }

        // pilihan dari dropdown master kampus
        if ($campus !== '' && ctype_digit($campus)) {
            $campusName = (string) ($campusList->firstWhere('id', (int) $campus)?->name ?? '');

            if ($campusName === '') {
                return null;
            }

            return $major === '' ? $campusName : "{$major}, {$campusName}";
        }

        // tidak memilih kampus = kosongkan riwayat pendidikan
        return null;
    }

    /**
     * Nilai awal field pendidikan pada form: mencocokkan data lama
     * (teks bebas, mis. hasil import Excel) dengan master kampus
     * agar dropdown terpilih otomatis saat form dibuka.
     *
     * @return array<int, array{campus: string, major: string, custom: string}>
     */
    private function educationFieldDefaults(Employee $employee, $campusList = null): array
    {
        $campusList ??= Campus::orderBy('sort_order')->orderBy('name')->get();

        $defaults = [];

        foreach ([1, 2, 3] as $i) {
            $value = trim((string) $employee->{"education_{$i}"});
            $defaults[$i] = ['campus' => '', 'major' => '', 'custom' => ''];

            if ($value === '') {
                continue;
            }

            foreach ($campusList as $campus) {
                if ($campus->name !== '' && stripos($value, $campus->name) !== false) {
                    $defaults[$i]['campus'] = (string) $campus->id;
                    $defaults[$i]['major'] = trim(str_ireplace($campus->name, '', $value), " \t\n\r\0\x0B,;-–—");
                    continue 2;
                }
            }

            // data lama tidak cocok dengan master kampus -> tampilkan sebagai isian manual
            $defaults[$i]['campus'] = 'custom';
            $defaults[$i]['custom'] = $value;
        }

        return $defaults;
    }

    private function syncPosition(Employee $employee, Request $request): void
    {
        $positionId = (int) $request->input('position_id');
        $current = $employee->currentPosition;

        if ($current && $current->position_id === $positionId) {
            return;
        }

        if ($current) {
            $current->update(['is_current' => false, 'end_date' => now()]);
        }

        $employee->positions()->create([
            'position_id' => $positionId,
            'unit_id' => $request->input('unit_id'),
            'start_date' => $request->input('tmt_jabatan') ?: now()->toDateString(),
            'is_current' => true,
        ]);

        $position = Position::find($positionId);
        $employee->update(['position_name' => $position?->name]);
    }
}
