<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\Unit;
use App\Services\Notifier;
use App\Exports\GenericExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pengajuan cuti pegawai.
 *
 * Fitur menunggu kepastian tanda tangan digital (ttd digital) sehingga
 * default-nya NONAKTIF — dapat dinyalakan/dimatikan oleh Administrator Utama
 * dari menu Pengaturan -> Tampilan & Menu (menu "Pengajuan Cuti").
 *
 * Workflow (mengikuti layanan persuratan):
 *   Pegawai mengajukan (PENDING)
 *     -> Admin/Biro SDM memverifikasi (VERIFIED)
 *       -> Persetujuan akhir (APPROVED)
 *   Penolakan dapat terjadi pada tahap verifikasi/persetujuan (REJECTED).
 */
class LeaveRequestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // seluruh route cuti tidak tersedia saat fitur dinonaktifkan
            new Middleware(function ($request, $next) {
                abort_unless(Setting::menuVisible('cuti'), 404, 'Fitur pengajuan cuti sedang dinonaktifkan.');

                return $next($request);
            }),
        ];
    }

    /**
     * Daftar pengajuan cuti - untuk pegawai: miliknya saja, untuk admin: semua.
     * Filter jenis & status mendukung pilihan lebih dari satu (checklist).
     * Catatan Masukan 21 Okt 2026: tersedia filter rentang tanggal & unit
     * kerja + ringkasan monitoring (jumlah cuti yang telah diambil).
     */
    public function index(Request $request)
    {
        $leaves = $this->filteredQuery($request)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // ringkasan monitoring sesuai filter aktif
        $statsQuery = clone $this->filteredQuery($request);

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', LeaveRequest::STATUS_PENDING)->count(),
            'approved' => (clone $statsQuery)->where('status', LeaveRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $statsQuery)->where('status', LeaveRequest::STATUS_REJECTED)->count(),
            'days_taken' => (int) (clone $statsQuery)->where('status', LeaveRequest::STATUS_APPROVED)->sum('total_days'),
        ];

        return view('cuti.index', [
            'leaves' => $leaves,
            'stats' => $stats,
            'filters' => $request->only(['status', 'type', 'search', 'date_from', 'date_to', 'unit']),
            'unitOptions' => Unit::ordered()->get(),
        ]);
    }

    /**
     * Form pengajuan cuti (mengikuti formulir resmi
     * "FORM CUTI KOSONG - PNS dan PPPK" Kementerian Transmigrasi RI).
     */
    public function create(Request $request)
    {
        $user = $request->user();

        // data pegawai untuk autofill Bagian I (data pegawai) saat admin memilih pegawai
        // + posisi sisa cuti tahunan N-2 / N-1 / N tiap pegawai (Bagian V, otomatis sistem)
        // + skema jenis cuti per status kepegawaian (PNS / PPPK / Non ASN — Catatan 21 Okt 2026)
        $employees = $user->isPrivileged()
            ? Employee::query()
                ->with(['unit', 'employmentStatus'])
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'nip' => $e->nip,
                    'position_name' => $e->position_name,
                    'unit_name' => $e->unit?->name,
                    'masa_kerja' => $e->masa_kerja,
                    'scheme' => $e->leave_scheme,
                    'scheme_label' => $e->leave_scheme_label,
                    'balances' => LeaveRequest::annualBalances($e->id),
                ])
            : collect();

        // pegawai yang sedang login: data Bagian I terisi otomatis dari profilnya;
        // admin/HRD tetap dapat mengganti pegawai melalui pencarian nama pada form
        $defaultEmployeeId = $user->employee_id;
        $defaultEmployee = $defaultEmployeeId ? Employee::with('employmentStatus')->find($defaultEmployeeId) : null;

        return view('cuti.form', [
            'leave' => new LeaveRequest(['start_date' => now(), 'end_date' => now()]),
            'employees' => $employees,
            'defaultEmployeeId' => $defaultEmployeeId,
            'defaultScheme' => $defaultEmployee?->leave_scheme ?? null,
            'defaultSchemeLabel' => $defaultEmployee?->leave_scheme_label ?? null,
            'balances' => $defaultEmployeeId ? LeaveRequest::annualBalances($defaultEmployeeId) : null,
            'isPrivileged' => $user->isPrivileged(),
        ]);
    }

    /**
     * Simpan pengajuan cuti.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'type' => ['required', 'in:'.implode(',', array_keys(LeaveRequest::typeOptions()))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required'],
            'address_during_leave' => ['nullable', 'max:255'],
            'phone_during_leave' => ['nullable', 'max:30'],
            'annual_n2' => ['nullable', 'integer', 'min:0', 'max:366'],
            'annual_n1' => ['nullable', 'integer', 'min:0', 'max:366'],
            'annual_n' => ['nullable', 'integer', 'min:0', 'max:366'],
            'leave_note' => ['nullable', 'max:500'],
        ], [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'reason.required' => 'Alasan/keterangan pengajuan cuti wajib diisi.',
        ]);

        $user = $request->user();

        // pegawai yang sedang login dipakai sebagai default — mencegah salah pilih pegawai;
        // admin tetap dapat mengajukan untuk pegawai lain melalui pilihan pada formulir
        $employeeId = $user->isPrivileged()
            ? ($validated['employee_id'] ?? $user->employee_id)
            : $user->employee_id;

        abort_if(! $employeeId, 403, 'Akun Anda belum terhubung dengan data pegawai.');

        $employee = Employee::with(['employmentStatus', 'unit'])->find($employeeId);

        if (! $employee) {
            return back()->with('error', 'Data pegawai tidak ditemukan.')->withInput();
        }

        /* ---------- Ketentuan jenis cuti per status (Catatan 21 Okt 2026) ----------
         * PNS/CPNS : 7 jenis (Tahunan, Besar, Sakit, Melahirkan, Alasan Penting,
         *             Bersama, di Luar Tanggungan Negara);
         * PPPK     : Tahunan, Sakit, Melahirkan, Bersama;
         * Non ASN  : tidak dapat mengajukan cuti. */
        if ($employee->leave_scheme === 'NON_ASN') {
            return back()->with('error', 'Pegawai Non ASN tidak dapat mengajukan cuti melalui aplikasi ini.')->withInput();
        }

        $allowedTypes = array_keys(LeaveRequest::typeOptionsFor($employee));

        if (! in_array($validated['type'], $allowedTypes, true)) {
            return back()
                ->with('error', 'Jenis cuti "'.LeaveRequest::typeOptions()[$validated['type']].'" tidak tersedia untuk pegawai berstatus '.$employee->leave_scheme_label.'.')
                ->withInput();
        }

        /* ---------- Bagian V: catatan cuti (sisa N-2/N-1/N + keterangan) ----------
         * Pegawai: nominal dihitung OTOMATIS sistem (input readonly, abaikan kiriman).
         * Admin/HRD: boleh menyunting nominal & mengisi keterangan catatan cuti. */
        $year = (int) now()->format('Y');
        $auto = LeaveRequest::annualBalances($employeeId, $year);

        $balances = $user->isPrivileged()
            ? [
                'balance_year' => $year,
                'annual_n2' => $validated['annual_n2'] ?? $auto['n2'],
                'annual_n1' => $validated['annual_n1'] ?? $auto['n1'],
                'annual_n' => $validated['annual_n'] ?? $auto['n'],
                'leave_note' => $validated['leave_note'] ?? null,
            ]
            : [
                'balance_year' => $auto['year'],
                'annual_n2' => $auto['n2'],
                'annual_n1' => $auto['n1'],
                'annual_n' => $auto['n'],
                'leave_note' => null, // keterangan hanya diisi Admin/HRD
            ];

        $leave = LeaveRequest::create([
            'employee_id' => $employeeId,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_days' => Carbon::parse($validated['start_date'])
                ->diffInDays(Carbon::parse($validated['end_date'])) + 1,
            'reason' => $validated['reason'],
            'address_during_leave' => $validated['address_during_leave'] ?? null,
            'phone_during_leave' => $validated['phone_during_leave'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
        ] + $balances);

        AuditLog::record(AuditLog::EVENT_CREATE, 'cuti', 'Pengajuan cuti: '.$leave->employee?->name.' ('.$leave->type_label.')');

        // notifikasi email ke Administrator Utama (bila diaktifkan)
        Notifier::notifyAdmins(
            type: 'leave',
            title: 'Pengajuan Cuti Baru',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada pengajuan cuti baru yang menunggu verifikasi pada aplikasi Dashboard Biro OSDMRB.',
            ],
            fields: [
                'Pegawai' => $leave->employee?->name ?? '-',
                'Jenis Cuti' => $leave->type_label,
                'Rentang' => $leave->period_label,
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('leaves.show', $leave),
            actionText: 'Tinjau Pengajuan',
        );

        return redirect()->route('leaves.show', $leave)
            ->with('success', 'Pengajuan cuti berhasil dikirim dan menunggu verifikasi.');
    }

    /**
     * Detail pengajuan cuti + aksi workflow.
     */
    public function show(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);

        $leave->load(['employee.rank', 'employee.employmentStatus', 'employee.unit', 'verifier', 'approver']);

        return view('cuti.show', ['leave' => $leave]);
    }

    /**
     * Cetak / ekspor formulir permohonan cuti ke PDF — mengikuti tata letak
     * formulir resmi baku BKN (FORM CUTI — PNS & PPPK) sesuai status
     * kepegawaian pemohon (Catatan Masukan 21 Okt 2026).
     */
    public function print(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);

        $leave->load(['employee.rank', 'employee.employmentStatus', 'employee.unit', 'verifier', 'approver']);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti',
            'Cetak formulir cuti: '.$leave->employee?->name.' ('.$leave->type_label.')');

        $scheme = $leave->employee?->leave_scheme ?? 'PNS';

        return Pdf::loadView('cuti.pdf', ['leave' => $leave, 'scheme' => $scheme])
            ->setPaper('a4')
            ->stream(Str::slug('formulir-cuti-'.$leave->employee?->name).'.pdf');
    }

    /**
     * Verifikasi pengajuan (PENDING -> VERIFIED).
     */
    public function verify(Request $request, LeaveRequest $leave)
    {
        abort_unless($request->user()->can('verify letters'), 403);
        abort_unless($leave->status === LeaveRequest::STATUS_PENDING, 422, 'Pengajuan tidak berada pada status menunggu verifikasi.');

        $leave->update([
            'status' => LeaveRequest::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'note' => $request->input('note') ?: $leave->note,
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti', 'Verifikasi pengajuan cuti: '.$leave->employee?->name);

        return back()->with('success', 'Pengajuan cuti berhasil diverifikasi.');
    }

    /**
     * Persetujuan akhir (VERIFIED -> APPROVED).
     */
    public function approve(Request $request, LeaveRequest $leave)
    {
        abort_unless($request->user()->can('approve letters'), 403);
        abort_unless($leave->status === LeaveRequest::STATUS_VERIFIED, 422, 'Pengajuan harus diverifikasi terlebih dahulu.');

        $leave->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'note' => $request->input('note') ?: $leave->note,
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti', 'Persetujuan pengajuan cuti: '.$leave->employee?->name);

        return back()->with('success', 'Pengajuan cuti disetujui.');
    }

    /**
     * Penolakan (PENDING/VERIFIED -> REJECTED).
     */
    public function reject(Request $request, LeaveRequest $leave)
    {
        abort_unless($request->user()->can('verify letters'), 403);
        abort_unless(in_array($leave->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_VERIFIED]), 422);

        $validated = $request->validate(['note' => ['required']], [
            'note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $leave->update(['status' => LeaveRequest::STATUS_REJECTED, 'note' => $validated['note']]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti', 'Penolakan pengajuan cuti: '.$leave->employee?->name);

        return back()->with('success', 'Pengajuan cuti ditolak.');
    }

    /**
     * Batalkan pengajuan oleh pegawai pemilik (hanya PENDING).
     */
    public function destroy(Request $request, LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);
        abort_unless($leave->status === LeaveRequest::STATUS_PENDING, 422, 'Hanya pengajuan berstatus menunggu verifikasi yang dapat dibatalkan.');

        AuditLog::record(AuditLog::EVENT_DELETE, 'cuti', 'Membatalkan pengajuan cuti: '.$leave->employee?->name);

        $leave->delete();

        return redirect()->route('leaves.index')
            ->with('success', 'Pengajuan cuti berhasil dibatalkan.');
    }

    /* ================= HELPERS ================= */

    /**
     * Rekap & monitoring cuti per pegawai (Catatan Masukan 21 Okt 2026):
     * jumlah cuti yang telah diambil (disetujui) per jenis + total hari,
     * dengan filter rentang tanggal dan unit kerja.
     * Akses: Admin Bagian, Biro SDM & Super Admin.
     */
    public function rekap(Request $request)
    {
        [$rows, $filters] = $this->rekapQuery($request);

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('cuti.rekap', [
            'items' => $items,
            'grandTotal' => [
                'employees' => $rows->count(),
                'requests' => (int) $rows->sum('total_requests'),
                'days' => (int) $rows->sum('total_days'),
            ],
            'filters' => $filters,
            'unitOptions' => Unit::ordered()->get(),
        ]);
    }

    /**
     * Export rekap cuti per pegawai ke Excel (mengikuti filter aktif).
     */
    public function rekapExport(Request $request)
    {
        [$rows] = $this->rekapQuery($request);

        if ($rows->isEmpty()) {
            return back()->with('error', 'Tidak ada data rekap cuti untuk diekspor.');
        }

        $headings = ['No', 'Nama Pegawai', 'NIP', 'Unit Kerja', 'Status',
            'Tahunan (hari)', 'Besar (hari)', 'Sakit (hari)', 'Melahirkan (hari)',
            'Alasan Penting (hari)', 'Bersama (hari)', 'CLTN (hari)',
            'Total Hari Cuti', 'Jumlah Pengajuan'];

        $export = $rows->values()->map(fn (array $r, int $i) => [
            $i + 1, $r['name'], $r['nip'], $r['unit'], $r['status'],
            $r['days'][LeaveRequest::TYPE_TAHUNAN] ?? 0,
            $r['days'][LeaveRequest::TYPE_BESAR] ?? 0,
            $r['days'][LeaveRequest::TYPE_SAKIT] ?? 0,
            $r['days'][LeaveRequest::TYPE_MELAHIRKAN] ?? 0,
            $r['days'][LeaveRequest::TYPE_ALASAN_PENTING] ?? 0,
            $r['days'][LeaveRequest::TYPE_BERSAMA] ?? 0,
            $r['days'][LeaveRequest::TYPE_CLTN] ?? 0,
            $r['total_days'], $r['total_requests'],
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti', 'Ekspor rekap cuti ('.$export->count().' pegawai)');

        return Excel::download(
            new GenericExport($export, $headings, 'Rekap Cuti'),
            'rekap-cuti-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /**
     * Query rekap cuti ter-agregasi per pegawai (hanya status DISSETUJUI —
     * cuti yang benar-benar telah diambil) + filter tanggal & unit kerja.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array}
     */
    private function rekapQuery(Request $request): array
    {
        $filters = $request->only(['date_from', 'date_to', 'unit', 'search']);

        $query = LeaveRequest::query()
            ->where('leave_requests.status', LeaveRequest::STATUS_APPROVED)
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->join('units', 'units.id', '=', 'employees.unit_id')
            ->selectRaw('leave_requests.employee_id, leave_requests.type,
                COUNT(*) as jumlah, SUM(leave_requests.total_days) as hari')
            ->groupBy('leave_requests.employee_id', 'leave_requests.type');

        // rentang tanggal: cuti yang BERIRINGAN (overlap) dengan periode terpilih
        if (! empty($filters['date_from'])) {
            $query->whereDate('leave_requests.end_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('leave_requests.start_date', '<=', $filters['date_to']);
        }

        // unit kerja terpilih beserta seluruh turunannya
        $unitIds = collect($filters['unit'] ?? [])->filter()->values();

        if ($unitIds->isNotEmpty()) {
            $all = $unitIds->map(fn ($v) => (int) $v)->unique()->values();
            $parents = $all->values();

            while ($parents->isNotEmpty()) {
                $parents = Unit::whereIn('parent_id', $parents)->pluck('id');
                $all = $all->merge($parents)->unique()->values();
            }

            $query->whereIn('employees.unit_id', $all->all());
        }

        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('employees.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('employees.nip', 'like', '%'.$filters['search'].'%'));
        }

        $employees = Employee::query()->with(['unit', 'employmentStatus'])
            ->whereIn('id', (clone $query)->pluck('leave_requests.employee_id')->unique())
            ->get()
            ->keyBy('id');

        $rows = collect();

        foreach ($query->get() as $row) {
            $employee = $employees->get($row->employee_id);

            if (! $employee) {
                continue;
            }

            $item = $rows->get($row->employee_id, [
                'id' => $employee->id,
                'name' => $employee->name,
                'nip' => $employee->nip,
                'unit' => $employee->unit?->name ?? '-',
                'status' => $employee->display_status,
                'days' => [],
                'counts' => [],
                'total_days' => 0,
                'total_requests' => 0,
            ]);

            $item['days'][$row->type] = (int) $row->hari;
            $item['counts'][$row->type] = (int) $row->jumlah;
            $item['total_days'] += (int) $row->hari;
            $item['total_requests'] += (int) $row->jumlah;

            $rows->put($row->employee_id, $item);
        }

        return [$rows->sortBy(fn ($r) => mb_strtolower($r['name']))->values(), $filters];
    }

    /**
     * Query pengajuan cuti dengan filter (dipakai index).
     * Jenis cuti & status mendukung pilihan LEBIH DARI SATU (checklist)
     * serta tautan lama satu nilai (?status=PENDING).
     * Catatan 30 Sept 2026: pencarian hanya untuk Admin Bagian & Super Admin.
     */
    private function filteredQuery(Request $request)
    {
        $user = $request->user();

        $asArray = fn ($value) => collect(is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]))->filter()->values();

        $canSearch = $user->isAdmin() || $user->isSuperAdmin();

        return LeaveRequest::query()
            ->with(['employee.employmentStatus', 'employee.unit', 'verifier', 'approver'])
            ->when(! $user->isPrivileged(), fn ($q) => $q->where('employee_id', $user->employee_id))
            ->when($asArray($request->status)->isNotEmpty(), fn ($q) => $q->whereIn('status', $asArray($request->status)))
            ->when($asArray($request->type)->isNotEmpty(), fn ($q) => $q->whereIn('type', $asArray($request->type)))
            /* filter rentang tanggal — pengajuan yang beririsan (overlap)
               dengan periode terpilih (Catatan 21 Okt 2026) */
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('end_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('start_date', '<=', $v))
            /* filter unit kerja terpilih + seluruh turunannya */
            ->when($asArray($request->unit)->isNotEmpty(), function ($q) use ($asArray, $request) {
                $all = $asArray($request->unit)->map(fn ($v) => (int) $v)->unique()->values();
                $parents = $all->values();

                while ($parents->isNotEmpty()) {
                    $parents = Unit::whereIn('parent_id', $parents)->pluck('id');
                    $all = $all->merge($parents)->unique()->values();
                }

                $q->whereHas('employee', fn ($e) => $e->whereIn('unit_id', $all->all()));
            })
            ->when($canSearch && $request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('reason', 'like', "%{$v}%")
                ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$v}%"))));
    }

    private function authorizeLeave(LeaveRequest $leave): void
    {
        $user = request()->user();

        if (! $user->isPrivileged() && $leave->employee_id !== $user->employee_id) {
            abort(403, 'Anda tidak memiliki akses ke pengajuan cuti ini.');
        }
    }
}
