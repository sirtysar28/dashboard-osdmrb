<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Traits\ExportsTable;
use Illuminate\Http\Request;

/**
 * Audit log aktivitas & statistik pengunjung aplikasi.
 */
class AuditLogController extends Controller
{
    use ExportsTable;

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'event', 'start', 'end']);

        $logs = $this->filteredQuery($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'stats' => AuditLog::visitorStats(),
            'filters' => $filters,
            'events' => [
                AuditLog::EVENT_VISIT => 'Kunjungan',
                AuditLog::EVENT_LOGIN => 'Login',
                AuditLog::EVENT_LOGIN_FAILED => 'Login Gagal',
                AuditLog::EVENT_OTP => 'Verifikasi OTP',
                AuditLog::EVENT_LOGOUT => 'Logout',
                AuditLog::EVENT_CREATE => 'Tambah Data',
                AuditLog::EVENT_UPDATE => 'Ubah Data',
                AuditLog::EVENT_DELETE => 'Hapus Data',
                AuditLog::EVENT_PASSWORD => 'Ganti Password',
                AuditLog::EVENT_REGISTER => 'Registrasi',
            ],
        ]);
    }

    /**
     * Export audit log ke Excel / PDF.
     */
    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request->only(['search', 'event', 'start', 'end']))
            ->latest()
            ->get()
            ->map(fn (AuditLog $log) => [
                $log->created_at->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
                $log->user_name ?? 'Tamu',
                $log->event_label,
                $log->module ?? '-',
                $log->description ?? '-',
                $log->ip_address ?? '-',
            ]);

        return $this->exportTable(
            $request->input('format', 'xlsx'),
            'Audit Log & Statistik Pengunjung',
            ['Waktu', 'Pengguna', 'Aktivitas', 'Modul', 'Keterangan', 'IP'],
            $rows,
            'audit-log',
            'Total: '.$rows->count().' baris log',
        );
    }

    private function filteredQuery(array $filters)
    {
        return AuditLog::query()
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('user_name', 'like', "%{$v}%")
                ->orWhere('description', 'like', "%{$v}%")
                ->orWhere('ip_address', 'like', "%{$v}%")))
            ->when($filters['event'] ?? null, fn ($q, $v) => $q->where('event', $v))
            ->when($filters['start'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['end'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
