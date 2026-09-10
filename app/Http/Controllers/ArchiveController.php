<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveLoan;
use App\Models\Employee;
use App\Models\Unit;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Layanan Kearsipan (dokumen arsip).
 *
 * Admin instansi  : kelola seluruh arsip (tambah/ubah/hapus, unggah berkas, mutasi status).
 * Pegawai         : telusuri katalog arsip publik + detail + unduh salinan digital.
 */
class ArchiveController extends Controller
{
    use ExportsTable;

    /**
     * Katalog / daftar arsip.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $archives = $this->filteredQuery($request)
            ->latest('document_date')
            ->paginate(12)
            ->withQueryString();

        $data = [
            'archives' => $archives,
            'categories' => ArchiveCategory::orderBy('code')->get(),
            'types' => Archive::TYPES,
            'statuses' => Archive::STATUSES,
            'years' => Archive::select('year')->distinct()->whereNotNull('year')->orderByDesc('year')->pluck('year'),
            'filters' => $request->only(['search', 'category', 'type', 'year', 'status']),
        ];

        // statistik untuk admin
        if ($user->isPrivileged()) {
            $data['stats'] = [
                'total' => Archive::count(),
                'available' => Archive::where('status', 'TERSEDIA')->count(),
                'borrowed' => Archive::where('status', 'DIPINJAM')->count(),
                'inactive' => Archive::where('retention', 'INAKTIF')->count(),
                'permanent' => Archive::where('retention', 'PERMANEN')->count(),
                'loansPending' => ArchiveLoan::where('status', ArchiveLoan::STATUS_PENDING)->count(),
            ];
        } else {
            $data['myLoanStats'] = [
                'pending' => ArchiveLoan::where('employee_id', $user->employee_id)->where('status', ArchiveLoan::STATUS_PENDING)->count(),
                'approved' => ArchiveLoan::where('employee_id', $user->employee_id)->where('status', ArchiveLoan::STATUS_APPROVED)->count(),
            ];
        }

        return view('archives.index', $data);
    }

    /**
     * Form tambah arsip (admin).
     */
    public function create()
    {
        return view('archives.form', [
            'archive' => new Archive(['document_date' => now(), 'year' => now()->year]),
            'categories' => ArchiveCategory::orderBy('code')->get(),
            'employees' => Employee::orderBy('name')->limit(300)->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    /**
     * Simpan arsip baru (admin).
     */
    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['file_path'] = $request->file('file')?->store('archives', 'public');
        $validated['file_name'] = $request->file('file')?->getClientOriginalName();
        $validated['uploaded_by'] = $request->user()->id;

        Archive::create($validated);

        return redirect()->route('archives.index')
            ->with('success', 'Dokumen arsip berhasil ditambahkan.');
    }

    /**
     * Detail arsip + riwayat peminjaman.
     */
    public function show(Archive $archive)
    {
        $this->authorizeArchive($archive);

        $archive->load(['category', 'employee.employmentStatus', 'unit', 'letter.letterType', 'uploader', 'loans.employee']);

        return view('archives.show', [
            'archive' => $archive,
            'hasActiveLoan' => ArchiveLoan::where('archive_id', $archive->id)
                ->whereIn('status', [ArchiveLoan::STATUS_PENDING, ArchiveLoan::STATUS_APPROVED])
                ->exists(),
        ]);
    }

    /**
     * Form ubah arsip (admin).
     */
    public function edit(Archive $archive)
    {
        return view('archives.form', [
            'archive' => $archive,
            'categories' => ArchiveCategory::orderBy('code')->get(),
            'employees' => Employee::orderBy('name')->limit(300)->get(),
            'units' => Unit::orderBy('name')->get(),
        ]);
    }

    /**
     * Perbarui arsip (admin).
     */
    public function update(Request $request, Archive $archive)
    {
        $validated = $this->validated($request, $archive);

        if ($request->file('file')) {
            // hapus berkas lama
            if ($archive->file_path) {
                Storage::disk('public')->delete($archive->file_path);
            }
            $validated['file_path'] = $request->file('file')->store('archives', 'public');
            $validated['file_name'] = $request->file('file')->getClientOriginalName();
        }

        $archive->update($validated);

        return redirect()->route('archives.show', $archive)
            ->with('success', 'Dokumen arsip berhasil diperbarui.');
    }

    /**
     * Hapus arsip (admin).
     */
    public function destroy(Archive $archive)
    {
        if ($archive->file_path) {
            Storage::disk('public')->delete($archive->file_path);
        }

        $archive->delete();

        return redirect()->route('archives.index')
            ->with('success', 'Dokumen arsip berhasil dihapus.');
    }

    /**
     * Unduh salinan digital arsip.
     */
    public function download(Request $request, Archive $archive)
    {
        $this->authorizeArchive($archive);

        abort_unless($archive->file_path && Storage::disk('public')->exists($archive->file_path),
            404, 'Salinan digital arsip tidak tersedia.');

        return Storage::disk('public')->download($archive->file_path, $archive->file_name ?: basename($archive->file_path));
    }

    /**
     * Export katalog arsip ke Excel / PDF (mengikuti filter aktif).
     */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->latest('document_date')
            ->get()
            ->map(fn (Archive $archive) => [
                $archive->archive_number,
                $archive->title,
                Archive::TYPES[$archive->type] ?? $archive->type,
                $archive->category?->code ?? '-',
                $archive->document_date?->format('d/m/Y') ?? '-',
                $archive->year,
                $archive->employee?->name ?? '-',
                $archive->unit?->name ?? '-',
                Archive::RETENTIONS[$archive->retention] ?? $archive->retention,
                $archive->physical_location,
                Archive::STATUSES[$archive->status] ?? $archive->status,
            ]);

        $filterInfo = collect($request->only(['search', 'category', 'type', 'year', 'status']))
            ->filter()
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(', ');

        return $this->exportTable(
            $request->input('format', 'xlsx'),
            'Katalog Arsip',
            ['Nomor Arsip', 'Judul', 'Jenis', 'Klasifikasi', 'Tgl Dokumen', 'Tahun', 'Pegawai', 'Unit', 'Retensi', 'Lokasi Fisik', 'Status'],
            $rows,
            'data-arsip',
            'Total: '.$rows->count().' arsip',
            $filterInfo ?: null,
        );
    }

    /* ================= MASTER KLASIFIKASI (ADMIN) ================= */

    public function storeCategory(Request $request)
    {
        ArchiveCategory::create($request->validate([
            'code' => ['required', 'max:50', 'unique:archive_categories,code'],
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
        ]));

        return back()->with('success', 'Klasifikasi arsip berhasil ditambahkan.');
    }

    public function updateCategory(Request $request, ArchiveCategory $archive_category)
    {
        $archive_category->update($request->validate([
            'code' => ['required', 'max:50', Rule::unique('archive_categories', 'code')->ignore($archive_category)],
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
        ]));

        return back()->with('success', 'Klasifikasi arsip berhasil diperbarui.');
    }

    public function destroyCategory(ArchiveCategory $archive_category)
    {
        $archive_category->delete();

        return back()->with('success', 'Klasifikasi arsip berhasil dihapus.');
    }

    /* ================= HELPERS ================= */

    private function validated(Request $request, ?Archive $archive = null): array
    {
        return $request->validate([
            'archive_number' => ['required', 'max:100', Rule::unique('archives', 'archive_number')->ignore($archive?->id)],
            'title' => ['required', 'max:255'],
            'description' => ['nullable'],
            'archive_category_id' => ['nullable', 'exists:archive_categories,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'type' => ['required', 'in:SURAT_MASUK,SURAT_KELUAR,SK,KONTRAK,LAPORAN,DOKUMEN_PEGAWAI,LAINNYA'],
            'document_date' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'retention' => ['required', 'in:AKTIF,INAKTIF,MUSNAH,DINILAI_KEMBALI,PERMANEN'],
            'retention_years' => ['nullable', 'integer', 'min:0'],
            'retention_until' => ['nullable', 'date'],
            'physical_location' => ['nullable', 'max:150'],
            'status' => ['required', 'in:TERSEDIA,DIPINJAM,DIPINDAHKAN,DIMUSNAHKAN'],
            'visibility' => ['required', 'in:PUBLIK,INTERNAL'],
            'file' => ['nullable', 'file', 'max:10240'], // maks 10 MB
        ]);
    }

    private function authorizeArchive(Archive $archive): void
    {
        $user = request()->user();

        if (! $user->isPrivileged()
            && $archive->visibility === 'INTERNAL'
            && $archive->employee_id !== $user->employee_id) {
            abort(403, 'Arsip ini bersifat internal dan hanya dapat diakses admin instansi.');
        }
    }

    /**
     * Query katalog arsip dengan filter + batasan hak akses (dipakai index & export).
     */
    private function filteredQuery(Request $request)
    {
        $user = $request->user();

        return Archive::query()
            ->with(['category', 'employee', 'unit', 'letter.letterType'])
            ->withCount(['loans' => fn ($q) => $q->whereIn('status', [ArchiveLoan::STATUS_PENDING, ArchiveLoan::STATUS_APPROVED])])
            // pegawai hanya melihat arsip publik (milik instansi) atau arsip pribadinya
            ->when(! $user->isPrivileged(), function ($q) use ($user) {
                $q->where(function ($w) use ($user) {
                    $w->where('visibility', 'PUBLIK')
                        ->orWhere('employee_id', $user->employee_id);
                });
            })
            ->when($request->search, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$v}%")
                ->orWhere('archive_number', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")))
            ->when($request->category, fn ($q, $v) => $q->where('archive_category_id', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->year, fn ($q, $v) => $q->where('year', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v));
    }
}
