<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Services\Notifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
     */
    public function index(Request $request)
    {
        $leaves = $this->filteredQuery($request)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('cuti.index', [
            'leaves' => $leaves,
            'filters' => $request->only(['status', 'type', 'search']),
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
        $employees = $user->isPrivileged()
            ? Employee::query()
                ->with('unit')
                ->orderBy('name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'nip' => $e->nip,
                    'position_name' => $e->position_name,
                    'unit_name' => $e->unit?->name,
                    'masa_kerja' => $e->masa_kerja,
                    'balances' => LeaveRequest::annualBalances($e->id),
                ])
            : collect();

        // pegawai yang sedang login: data Bagian I terisi otomatis dari profilnya;
        // admin/HRD tetap dapat mengganti pegawai melalui pencarian nama pada form
        $defaultEmployeeId = $user->employee_id;

        return view('cuti.form', [
            'leave' => new LeaveRequest(['start_date' => now(), 'end_date' => now()]),
            'employees' => $employees,
            'defaultEmployeeId' => $defaultEmployeeId,
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
     * Cetak / ekspor formulir permohonan cuti ke PDF
     * (mengikuti tata letak formulir resmi Bagian I–VIII — siap print).
     */
    public function print(LeaveRequest $leave)
    {
        $this->authorizeLeave($leave);

        $leave->load(['employee.rank', 'employee.employmentStatus', 'employee.unit', 'verifier', 'approver']);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'cuti',
            'Cetak formulir cuti: '.$leave->employee?->name.' ('.$leave->type_label.')');

        return Pdf::loadView('cuti.pdf', ['leave' => $leave])
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
     * Query pengajuan cuti dengan filter (dipakai index).
     * Jenis cuti & status mendukung pilihan LEBIH DARI SATU (checklist)
     * serta tautan lama satu nilai (?status=PENDING).
     */
    private function filteredQuery(Request $request)
    {
        $user = $request->user();

        $asArray = fn ($value) => collect(is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]))->filter()->values();

        return LeaveRequest::query()
            ->with(['employee.employmentStatus', 'verifier', 'approver'])
            ->when(! $user->isPrivileged(), fn ($q) => $q->where('employee_id', $user->employee_id))
            ->when($asArray($request->status)->isNotEmpty(), fn ($q) => $q->whereIn('status', $asArray($request->status)))
            ->when($asArray($request->type)->isNotEmpty(), fn ($q) => $q->whereIn('type', $asArray($request->type)))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
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
