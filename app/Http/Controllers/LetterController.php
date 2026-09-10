<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterLog;
use App\Models\LetterType;
use App\Traits\ExportsTable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Layanan persuratan kepegawaian.
 *
 * Workflow:
 *   Pegawai mengajukan (PENDING)
 *     -> Admin memverifikasi (VERIFIED)
 *       -> Kepala/Admin menyetujui & menerbitkan nomor (APPROVED)
 *         -> Surat dapat dicetak PDF
 *   Penolakan dapat terjadi pada tahap verifikasi/persetujuan (REJECTED).
 */
class LetterController extends Controller
{
    use ExportsTable;

    /**
     * Daftar surat - untuk pegawai: miliknya saja, untuk admin: semua.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $letters = $this->filteredQuery($request)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('letters.index', [
            'letters' => $letters,
            'letterTypes' => LetterType::orderBy('name')->get(),
            'filters' => $request->only(['status', 'type', 'search']),
        ]);
    }

    /**
     * Form pengajuan surat.
     */
    public function create(Request $request)
    {
        $user = $request->user();

        return view('letters.form', [
            'letter' => new Letter(['letter_date' => now()]),
            'letterTypes' => LetterType::where('is_active', true)->orderBy('name')->get(),
            'employees' => $user->isPrivileged() ? Employee::orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * Simpan pengajuan surat.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'letter_type_id' => ['required', 'exists:letter_types,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'subject' => ['required', 'max:255'],
            'purpose' => ['required'],
            'letter_date' => ['required', 'date'],
            'meta' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $employeeId = $user->isPrivileged() ? ($validated['employee_id'] ?? null) : $user->employee_id;

        abort_if(! $employeeId, 403, 'Akun Anda belum terhubung dengan data pegawai.');

        $letter = Letter::create([
            'letter_type_id' => $validated['letter_type_id'],
            'employee_id' => $employeeId,
            'subject' => $validated['subject'],
            'purpose' => $validated['purpose'],
            'letter_date' => $validated['letter_date'],
            'meta' => $validated['meta'] ?? [],
            'status' => Letter::STATUS_PENDING,
        ]);

        $letter->logs()->create([
            'user_id' => $user->id,
            'action' => 'SUBMIT',
            'to_status' => Letter::STATUS_PENDING,
            'note' => 'Pengajuan surat oleh pegawai.',
        ]);

        \App\Models\AuditLog::record(\App\Models\AuditLog::EVENT_CREATE, 'surat', 'Pengajuan surat: '.$letter->subject);

        // notifikasi email pengajuan surat ke Administrator Utama
        \App\Services\Notifier::notifyAdmins(
            type: 'letter',
            title: 'Pengajuan Surat Baru',
            greeting: 'Halo Administrator Utama',
            lines: [
                'Ada pengajuan surat baru yang menunggu verifikasi pada aplikasi Dashboard Biro OSDMRB.',
            ],
            fields: [
                'Perihal' => $letter->subject,
                'Jenis' => $letter->letterType?->name ?? '-',
                'Pemohon' => $letter->employee?->name ?? '-',
                'Tanggal Surat' => $letter->letter_date?->translatedFormat('d F Y') ?? '-',
                'Waktu' => now()->setTimezone(config('app.timezone'))->format('d F Y H:i'),
            ],
            actionUrl: route('letters.show', $letter),
            actionText: 'Tinjau Pengajuan',
        );

        return redirect()->route('letters.show', $letter)
            ->with('success', 'Pengajuan surat berhasil dikirim dan menunggu verifikasi.');
    }

    /**
     * Detail surat + riwayat workflow.
     */
    public function show(Letter $letter)
    {
        $this->authorizeLetter($letter);

        $letter->load(['letterType', 'employee.rank', 'employee.employmentStatus', 'employee.unit',
            'employee.education', 'logs.user', 'verifier', 'approver']);

        return view('letters.show', compact('letter'));
    }

    /**
     * Verifikasi surat oleh admin (PENDING -> VERIFIED).
     */
    public function verify(Request $request, Letter $letter)
    {
        abort_unless($request->user()->can('verify letters'), 403);
        abort_unless($letter->status === Letter::STATUS_PENDING, 422, 'Surat tidak berada pada status menunggu verifikasi.');

        $letter->update([
            'status' => Letter::STATUS_VERIFIED,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $letter->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'VERIFY',
            'from_status' => Letter::STATUS_PENDING,
            'to_status' => Letter::STATUS_VERIFIED,
            'note' => $request->input('note'),
        ]);

        return back()->with('success', 'Surat berhasil diverifikasi.');
    }

    /**
     * Persetujuan akhir + penerbitan nomor surat (VERIFIED -> APPROVED).
     */
    public function approve(Request $request, Letter $letter)
    {
        abort_unless($request->user()->can('approve letters'), 403);
        abort_unless($letter->status === Letter::STATUS_VERIFIED, 422, 'Surat harus diverifikasi terlebih dahulu.');

        $letter->update([
            'status' => Letter::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'number' => $letter->number ?: $this->generateNumber($letter),
            'letter_date' => $letter->letter_date ?: now(),
        ]);

        $letter->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'APPROVE',
            'from_status' => Letter::STATUS_VERIFIED,
            'to_status' => Letter::STATUS_APPROVED,
            'note' => $request->input('note'),
        ]);

        // otomatis diarsipkan ke layanan kearsipan
        \App\Models\Archive::createFromLetter($letter);

        return back()->with('success', 'Surat disetujui dan nomor diterbitkan: '.$letter->number);
    }

    /**
     * Penolakan (PENDING/VERIFIED -> REJECTED).
     */
    public function reject(Request $request, Letter $letter)
    {
        abort_unless($request->user()->can('verify letters'), 403);
        abort_unless(in_array($letter->status, [Letter::STATUS_PENDING, Letter::STATUS_VERIFIED]), 422);

        $validated = $request->validate(['note' => ['required']]);

        $from = $letter->status;
        $letter->update(['status' => Letter::STATUS_REJECTED, 'note' => $validated['note']]);

        $letter->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'REJECT',
            'from_status' => $from,
            'to_status' => Letter::STATUS_REJECTED,
            'note' => $validated['note'],
        ]);

        return back()->with('success', 'Pengajuan surat ditolak.');
    }

    /**
     * Cetak PDF surat (hanya APPROVED).
     */
    public function print(Request $request, Letter $letter)
    {
        $this->authorizeLetter($letter);
        abort_unless($letter->status === Letter::STATUS_APPROVED, 422, 'Surat belum disetujui sehingga belum dapat dicetak.');

        $letter->load(['letterType', 'employee.rank', 'employee.employmentStatus', 'employee.unit', 'employee.education']);

        $letter->logs()->create([
            'user_id' => $request->user()->id,
            'action' => 'PRINT',
            'to_status' => Letter::STATUS_APPROVED,
            'note' => 'Surat dicetak/diunduh PDF.',
        ]);

        return Pdf::loadView('letters.pdf', [
            'letter' => $letter,
            'employee' => $letter->employee,
        ])->setPaper('a4')->stream(Str::slug($letter->number ?: 'surat').'.pdf');
    }

    /**
     * Batalkan pengajuan oleh pegawai pemilik (PENDING -> DRAFT dihapus).
     */
    public function destroy(Request $request, Letter $letter)
    {
        $this->authorizeLetter($letter);
        abort_unless($letter->status === Letter::STATUS_PENDING, 422, 'Hanya pengajuan berstatus menunggu verifikasi yang dapat dibatalkan.');

        $letter->delete();

        return redirect()->route('letters.index')
            ->with('success', 'Pengajuan surat berhasil dibatalkan.');
    }

    /**
     * Export daftar surat ke Excel / PDF (mengikuti filter aktif).
     */
    public function export(Request $request)
    {
        $statusLabels = [
            Letter::STATUS_PENDING => 'Menunggu Verifikasi',
            Letter::STATUS_VERIFIED => 'Terverifikasi',
            Letter::STATUS_APPROVED => 'Disetujui',
            Letter::STATUS_REJECTED => 'Ditolak',
        ];

        $rows = $this->filteredQuery($request)
            ->latest()
            ->get()
            ->map(fn (Letter $letter) => [
                $letter->number ?? '(belum terbit)',
                $letter->employee?->name ?? '-',
                $letter->letterType?->name ?? '-',
                Str::limit($letter->subject, 80),
                $letter->letter_date?->format('d/m/Y') ?? $letter->created_at->format('d/m/Y'),
                $statusLabels[$letter->status] ?? $letter->status,
                $letter->approved_at?->format('d/m/Y H:i') ?? '-',
            ]);

        $filterInfo = collect($request->only(['search', 'status', 'type']))
            ->filter()
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(', ');

        return $this->exportTable(
            $request->input('format', 'xlsx'),
            'Layanan Persuratan',
            ['Nomor Surat', 'Pegawai', 'Jenis', 'Perihal', 'Tanggal', 'Status', 'Disetujui'],
            $rows,
            'data-surat',
            'Total: '.$rows->count().' surat',
            $filterInfo ?: null,
        );
    }

    /* ================= HELPERS ================= */

    /**
     * Query surat dengan filter (dipakai index & export).
     */
    private function filteredQuery(Request $request)
    {
        $user = $request->user();

        return Letter::query()
            ->with(['letterType', 'employee.employmentStatus', 'verifier', 'approver'])
            ->when(! $user->isPrivileged(), fn ($q) => $q->where('employee_id', $user->employee_id))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->type, fn ($q, $v) => $q->where('letter_type_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('subject', 'like', "%{$v}%")
                ->orWhere('number', 'like', "%{$v}%")
                ->orWhereHas('employee', fn ($e) => $e->where('name', 'like', "%{$v}%"))));
    }

    private function authorizeLetter(Letter $letter): void
    {
        $user = request()->user();

        if (! $user->isPrivileged() && $letter->employee_id !== $user->employee_id) {
            abort(403, 'Anda tidak memiliki akses ke surat ini.');
        }
    }

    /**
     * Generate nomor surat berdasarkan format jenis surat.
     */
    private function generateNumber(Letter $letter): string
    {
        $sequence = Letter::whereYear('approved_at', now()->year)
            ->where('letter_type_id', $letter->letter_type_id)
            ->count() + 1;

        $format = $letter->letterType->code_format ?: '{no}/OSDMRB/{romawi}/{tahun}';

        $romawi = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) now()->format('n')];

        return str_replace(
            ['{no}', '{romawi}', '{tahun}'],
            [str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), $romawi, now()->format('Y')],
            $format
        );
    }
}
