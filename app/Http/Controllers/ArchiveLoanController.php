<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveLoan;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Layanan peminjaman arsip.
 *
 * Workflow:
 *   Pegawai mengajukan peminjaman (PENDING)
 *     -> Admin menyetujui & arsip berstatus DIPINJAM (APPROVED)
 *       -> Pegawai mengembalikan / admin menandai kembali (RETURNED, arsip kembali TERSEDIA)
 *     -> atau ditolak (REJECTED)
 */
class ArchiveLoanController extends Controller
{
    use ExportsTable;

    /**
     * Daftar peminjaman — admin melihat semua, pegawai miliknya.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $loans = $this->filteredQuery($request)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('archives.loans', [
            'loans' => $loans,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Export daftar peminjaman arsip ke Excel / PDF.
     */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->latest()
            ->get()
            ->map(fn (ArchiveLoan $loan) => [
                $loan->archive?->archive_number ?? '-',
                $loan->archive?->title ?? '-',
                $loan->employee?->name ?? '-',
                Str::limit($loan->purpose, 70),
                $loan->loan_date?->format('d/m/Y'),
                $loan->due_date?->format('d/m/Y'),
                $loan->returned_at?->format('d/m/Y') ?? '-',
                $loan->status_label,
                $loan->handler?->name ?? '-',
            ]);

        return $this->exportTable(
            $request->input('format', 'xlsx'),
            'Peminjaman Arsip',
            ['Nomor Arsip', 'Judul Arsip', 'Peminjam', 'Keperluan', 'Tgl Pinjam', 'Jatuh Tempo', 'Dikembalikan', 'Status', 'Petugas'],
            $rows,
            'data-peminjaman-arsip',
            'Total: '.$rows->count().' transaksi',
            $request->status ? 'status='.$request->status : null,
        );
    }

    /* ================= HELPERS ================= */

    private function filteredQuery(Request $request)
    {
        $user = $request->user();

        return ArchiveLoan::query()
            ->with(['archive.category', 'employee', 'handler'])
            ->when(! $user->isPrivileged(), fn ($q) => $q->where('employee_id', $user->employee_id))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));
    }

    /**
     * Pegawai mengajukan peminjaman arsip.
     */
    public function store(Request $request, Archive $archive)
    {
        $user = $request->user();

        abort_if(! $user->employee_id, 403, 'Akun Anda belum terhubung dengan data pegawai.');
        abort_unless($archive->is_borrowable, 422, 'Arsip sedang tidak tersedia untuk dipinjam.');
        abort_if(ArchiveLoan::where('archive_id', $archive->id)
            ->whereIn('status', [ArchiveLoan::STATUS_PENDING, ArchiveLoan::STATUS_APPROVED])
            ->exists(), 422, 'Arsip ini sudah diajukan/sedang dipinjam.');

        $validated = $request->validate([
            'purpose' => ['required'],
            'loan_date' => ['required', 'date', 'after_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:loan_date'],
        ]);

        ArchiveLoan::create($validated + [
            'archive_id' => $archive->id,
            'employee_id' => $user->employee_id,
            'status' => ArchiveLoan::STATUS_PENDING,
        ]);

        return redirect()->route('archive-loans.index')
            ->with('success', 'Pengajuan peminjaman arsip terkirim, menunggu persetujuan admin.');
    }

    /**
     * Admin menyetujui peminjaman (PENDING -> APPROVED, arsip jadi DIPINJAM).
     */
    public function approve(Request $request, ArchiveLoan $loan)
    {
        abort_unless($request->user()->isPrivileged(), 403);
        abort_unless($loan->status === ArchiveLoan::STATUS_PENDING, 422, 'Pengajuan ini sudah diproses.');

        $loan->update([
            'status' => ArchiveLoan::STATUS_APPROVED,
            'handled_by' => $request->user()->id,
        ]);
        $loan->archive->update(['status' => 'DIPINJAM']);

        return back()->with('success', 'Peminjaman disetujui. Status arsip kini DIPINJAM.');
    }

    /**
     * Admin menolak peminjaman.
     */
    public function reject(Request $request, ArchiveLoan $loan)
    {
        abort_unless($request->user()->isPrivileged(), 403);
        abort_unless($loan->status === ArchiveLoan::STATUS_PENDING, 422, 'Pengajuan ini sudah diproses.');

        $validated = $request->validate(['note' => ['required']]);

        $loan->update([
            'status' => ArchiveLoan::STATUS_REJECTED,
            'handled_by' => $request->user()->id,
            'note' => $validated['note'],
        ]);

        return back()->with('success', 'Pengajuan peminjaman ditolak.');
    }

    /**
     * Pengembalian arsip (APPROVED -> RETURNED, arsip kembali TERSEDIA).
     */
    public function returned(Request $request, ArchiveLoan $loan)
    {
        $user = $request->user();

        abort_unless($user->isPrivileged() || $loan->employee_id === $user->employee_id, 403);
        abort_unless($loan->status === ArchiveLoan::STATUS_APPROVED, 422, 'Peminjaman ini tidak sedang aktif.');

        $loan->update([
            'status' => ArchiveLoan::STATUS_RETURNED,
            'returned_at' => now(),
            'handled_by' => $user->isPrivileged() ? $user->id : $loan->handled_by,
        ]);
        $loan->archive->update(['status' => 'TERSEDIA']);

        return back()->with('success', 'Arsip dikembalikan. Status arsip kembali TERSEDIA.');
    }
}
