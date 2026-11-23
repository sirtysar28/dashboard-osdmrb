<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * PENGAJUAN LAYANAN KEPEGAWAIAN — Catatan Masukan 19 Nov 2026.
 *
 * Menu baru pada grup "Layanan Kepegawaian":
 * - Form Pengajuan Layanan: Unit Kerja (dropdown), Nama yang mengajukan
 *   (dropdown), Jenis Layanan (dropdown), unggah Nota Dinas + link Bukti
 *   Dukung, tombol "Kirim Pengajuan".
 * - Daftar Pengajuan di bawah form: dibatasi 10 baris per halaman
 *   (pagination). Pegawai melihat pengajuannya sendiri, Admin/Biro SDM/
 *   Super Admin melihat semua pengajuan + memprosesnya.
 *
 * Basis layanan: pegawai ASN (PNS/CPNS & PPPK) aktif.
 */
class ServiceRequestController extends Controller
{
    /**
     * Halaman Pengajuan Layanan: form + daftar pengajuan (10 per halaman).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $privileged = $user->isPrivileged();

        $query = ServiceRequest::query()
            ->with(['employee:id,name,nip', 'unit:id,name'])
            ->latest();

        // pegawai biasa hanya melihat pengajuannya sendiri
        if (! $privileged) {
            $query->where('employee_id', $user->employee_id ?? 0);
        }

        // filter daftar (jenis layanan & status utk semua; unit & pencarian utk admin)
        if ($jenis = $request->input('jenis')) {
            $query->where('service_type', $jenis);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($privileged && $unit = $request->input('unit')) {
            $query->where('unit_id', $unit);
        }

        if ($privileged && $search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', fn ($e) => $e->where('name', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%"));
            });
        }

        // Catatan 19 Nov 2026: daftar dibatasi 10 item per halaman
        $requests = $query->paginate(10)->withQueryString();

        // data form — pegawai ASN aktif sebagai pilihan "Nama yang mengajukan"
        $employees = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->orderBy('name')
            ->get(['id', 'name', 'nip', 'unit_id'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'nip' => $e->nip,
                'unit_id' => $e->unit_id,
                'label' => $e->name.($e->nip ? ' — '.$e->nip : ''),
            ]);

        return view('layanan.index', [
            'requests' => $requests,
            'filters' => $request->only(['jenis', 'status', 'unit', 'search']),
            'unitOptions' => Unit::ordered()->get(),
            'employees' => $privileged ? $employees : collect(),
            'defaultEmployeeId' => $user->employee_id,
            'defaultEmployee' => $user->employee_id ? Employee::find($user->employee_id) : null,
            'types' => ServiceRequest::typeOptions(),
        ]);
    }

    /**
     * Simpan pengajuan layanan baru (tombol "Kirim Pengajuan").
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'service_type' => ['required', 'in:'.implode(',', array_keys(ServiceRequest::typeOptions()))],
            'nota' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'supporting_link' => ['nullable', 'url', 'max:500'],
        ], [
            'unit_id.required' => 'Unit kerja wajib dipilih.',
            'service_type.required' => 'Jenis layanan wajib dipilih.',
            'service_type.in' => 'Jenis layanan tidak dikenal.',
            'nota.required' => 'Unggah Nota Dinas wajib dilampirkan (PDF/JPG/PNG, maks. 10 MB).',
            'nota.mimes' => 'Nota Dinas harus berupa berkas PDF/JPG/PNG.',
            'nota.max' => 'Ukuran Nota Dinas maksimal 10 MB.',
            'supporting_link.url' => 'Link Bukti Dukung harus berupa alamat URL yang valid (mis. https://...).',
        ]);

        $user = $request->user();

        // pegawai hanya dapat mengajukan untuk dirinya sendiri; admin boleh untuk pegawai lain
        $employeeId = $user->isPrivileged()
            ? ($validated['employee_id'] ?? $user->employee_id)
            : $user->employee_id;

        abort_if(! $employeeId, 403, 'Akun Anda belum terhubung dengan data pegawai.');

        $employee = Employee::find($employeeId);

        if (! $employee) {
            return back()->with('error', 'Data pegawai tidak ditemukan.')->withInput();
        }

        // basis layanan: pegawai ASN saja
        if ($employee->employee_type === Employee::TYPE_NON_ASN) {
            return back()->with('error', 'Pengajuan layanan hanya tersedia bagi pegawai ASN (PNS/CPNS/PPPK).')->withInput();
        }

        $service = ServiceRequest::create([
            'unit_id' => $validated['unit_id'],
            'employee_id' => $employee->id,
            'service_type' => $validated['service_type'],
            'nota_path' => $request->file('nota')->store('layanan', 'public'),
            'nota_name' => $request->file('nota')->getClientOriginalName(),
            'supporting_link' => $validated['supporting_link'] ?? null,
            'status' => ServiceRequest::STATUS_PENDING,
        ]);

        AuditLog::record(AuditLog::EVENT_CREATE, 'layanan',
            'Pengajuan layanan: '.$employee->name.' ('.$service->type_label.')');

        // notifikasi email ke Administrator Utama (bila dikonfigurasi)
        Notifier::notifyAdmins(
            type: 'layanan',
            title: 'Pengajuan Layanan Baru',
            greeting: 'Ada pengajuan layanan kepegawaian baru yang perlu ditindaklanjuti.',
            lines: [
                'Pegawai: '.$employee->name.($employee->nip ? ' ('.$employee->nip.')' : ''),
                'Jenis Layanan: '.$service->type_label,
                'Unit Kerja: '.$service->unit?->name,
            ],
            actionUrl: route('layanan.index'),
            actionText: 'Buka Daftar Pengajuan',
        );

        return redirect()->route('layanan.index')
            ->with('success', 'Pengajuan layanan "'.$service->type_label.'" berhasil dikirim dan menunggu diproses Biro.');
    }

    /**
     * Unduh Nota Dinas — pemilik pengajuan atau Admin/Biro SDM/Super Admin.
     */
    public function download(Request $request, ServiceRequest $service_request)
    {
        abort_unless($request->user()->isPrivileged() || $service_request->employee_id === $request->user()->employee_id,
            403, 'Anda tidak memiliki akses ke berkas ini.');

        abort_unless($service_request->nota_path && Storage::disk('public')->exists($service_request->nota_path),
            404, 'Nota Dinas tidak tersedia.');

        return Storage::disk('public')->download(
            $service_request->nota_path,
            $service_request->nota_name ?: basename($service_request->nota_path)
        );
    }

    /**
     * Proses pengajuan (PENDING -> DIPROSES) — Admin/Biro SDM/Super Admin.
     */
    public function process(Request $request, ServiceRequest $service_request)
    {
        $this->authorizeManage($request);

        if ($service_request->status !== ServiceRequest::STATUS_PENDING) {
            return back()->with('error', 'Pengajuan ini sudah tidak berstatus "Diajukan".');
        }

        $service_request->update([
            'status' => ServiceRequest::STATUS_PROCESSING,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'note' => $request->input('note') ?: $service_request->note,
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'layanan',
            'Proses pengajuan layanan: '.$service_request->employee?->name.' ('.$service_request->type_label.')');

        return back()->with('success', 'Pengajuan layanan ditandai sedang diproses.');
    }

    /**
     * Selesaikan pengajuan (-> SELESAI) — Admin/Biro SDM/Super Admin.
     */
    public function complete(Request $request, ServiceRequest $service_request)
    {
        $this->authorizeManage($request);

        if ($service_request->status === ServiceRequest::STATUS_DONE) {
            return back()->with('error', 'Pengajuan ini sudah selesai.');
        }

        if ($service_request->status === ServiceRequest::STATUS_REJECTED) {
            return back()->with('error', 'Pengajuan ini sudah ditolak.');
        }

        $service_request->update([
            'status' => ServiceRequest::STATUS_DONE,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'note' => $request->input('note') ?: $service_request->note,
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'layanan',
            'Selesaikan pengajuan layanan: '.$service_request->employee?->name.' ('.$service_request->type_label.')');

        return back()->with('success', 'Pengajuan layanan ditandai selesai.');
    }

    /**
     * Tolak pengajuan (-> DITOLAK, wajib alasan) — Admin/Biro SDM/Super Admin.
     */
    public function reject(Request $request, ServiceRequest $service_request)
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'note' => ['required', 'max:500'],
        ], [
            'note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        if (in_array($service_request->status, [ServiceRequest::STATUS_DONE, ServiceRequest::STATUS_REJECTED], true)) {
            return back()->with('error', 'Pengajuan ini tidak dapat ditolak lagi.');
        }

        $service_request->update([
            'status' => ServiceRequest::STATUS_REJECTED,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
            'note' => $validated['note'],
        ]);

        AuditLog::record(AuditLog::EVENT_UPDATE, 'layanan',
            'Tolak pengajuan layanan: '.$service_request->employee?->name.' ('.$service_request->type_label.')');

        return back()->with('success', 'Pengajuan layanan ditolak.');
    }

    /**
     * Hapus pengajuan — pemilik (selama belum selesai) atau Admin/Super Admin.
     */
    public function destroy(Request $request, ServiceRequest $service_request)
    {
        $user = $request->user();
        $isOwner = $service_request->employee_id === $user->employee_id;

        abort_unless($isOwner || $user->isAdmin() || $user->isSuperAdmin(),
            403, 'Anda tidak memiliki akses untuk menghapus pengajuan ini.');

        if ($isOwner && ! $user->isPrivileged()
            && $service_request->status === ServiceRequest::STATUS_DONE) {
            return back()->with('error', 'Pengajuan yang sudah selesai tidak dapat dihapus.');
        }

        if ($service_request->nota_path) {
            Storage::disk('public')->delete($service_request->nota_path);
        }

        $label = $service_request->employee?->name.' ('.$service_request->type_label.')';
        $service_request->delete();

        AuditLog::record(AuditLog::EVENT_DELETE, 'layanan', 'Hapus pengajuan layanan: '.$label);

        return back()->with('success', 'Pengajuan layanan berhasil dihapus.');
    }

    /** Hanya Admin Bagian, Biro SDM & Super Admin yang boleh memproses. */
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->isPrivileged(), 403,
            'Hanya Admin Bagian, Biro SDM & Super Admin yang dapat memproses pengajuan.');
    }
}
