<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use App\Exports\EmployeesTemplateExport;
use App\Imports\EmployeesImport;
use App\Imports\NonAsnEmployeesImport;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\Rank;
use App\Models\Unit;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    use ExportsTable;

    /**
     * Daftar pegawai ASN (admin).
     * Mendukung filter dari stat-card dashboard: jenis jabatan, pensiun, status.
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
            'filters' => $request->only(['search', 'status', 'unit', 'inactive', 'jenis', 'pensiun', 'naik']),
            'nonAsn' => false,
        ]);
    }

    /**
     * Daftar pegawai NON ASN (pramubakti, security, cleaning service, dll).
     */
    public function nonAsn(Request $request)
    {
        $employees = Employee::query()
            ->with(['unit', 'employmentStatus', 'education'])
            ->where('employee_type', Employee::TYPE_NON_ASN)
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
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
            'positions.position.positionType', 'positions.position.jobLevel', 'positions.unit', 'letters.letterType']);

        return view('employees.show', [
            'employee' => $employee,
            'isOwnProfile' => true,
        ]);
    }

    /**
     * Detail pegawai.
     * Pegawai biasa hanya boleh melihat profil miliknya sendiri.
     */
    public function show(Employee $employee)
    {
        $user = request()->user();

        abort_if(! $user->isPrivileged() && $user->employee_id !== $employee->id,
            403, 'Anda tidak memiliki akses untuk melihat profil pegawai lain.');

        $employee->load(['unit', 'education', 'rank', 'employmentStatus',
            'positions.position.positionType', 'positions.position.jobLevel', 'positions.unit', 'letters.letterType']);

        return view('employees.show', [
            'employee' => $employee,
            'isOwnProfile' => false,
        ]);
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

        $employee->update($validated);

        if ($request->filled('position_id')) {
            $this->syncPosition($employee, $request);
        }

        AuditLog::record(AuditLog::EVENT_UPDATE, 'pegawai', 'Mengubah data pegawai: '.$employee->name);

        return redirect()->route('employees.show', $employee)
            ->with('success', 'Data pegawai berhasil diperbarui.');
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

        $filterInfo = collect($request->only(['search', 'status', 'unit', 'inactive']))
            ->filter()
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(', ');

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
     * Query pegawai dengan filter pencarian/status/unit/aktif (dipakai index & export)
     * + filter stat-card dashboard (jenis jabatan, pensiun, kenaikan jabatan).
     */
    private function filteredQuery(Request $request)
    {
        return Employee::query()
            ->with(['unit', 'education', 'rank', 'employmentStatus'])
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN) // data utama = ASN
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('nip', 'like', "%{$v}%")))
            ->when($request->status === 'pppk', fn ($q) => $q->whereHas(
                'employmentStatus', fn ($w) => $w->where('name', 'like', 'PPPK%')))
            ->when($request->status && $request->status !== 'pppk', fn ($q, $v) => $q->where('employment_status_id', $v))
            ->when($request->unit, fn ($q, $v) => $q->where('unit_id', $v))
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
             * Opsi: tahun_ini | 1 (≤ 1 tahun) | 4 (≤ 4 tahun).
             * Window TMT digeser -4 tahun agar kueri kompatibel MySQL & SQLite.
             */
            ->when(in_array($request->naik, ['tahun_ini', '1', '4'], true), function ($q) use ($request) {
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
