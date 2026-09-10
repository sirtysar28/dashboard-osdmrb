<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat setiap kunjungan halaman pengguna terautentikasi
 * ke audit log — menjadi sumber statistik pengunjung aplikasi.
 *
 * Satu sesi hanya dicatat 1x per menit per URL agar tabel tidak membengkak.
 */
class LogAuditVisit
{
    protected const SESSION_KEY = 'audit_visit_logged';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($request->user() && $request->isMethodSafe() && ! $request->ajax() && ! $request->expectsJson()) {
                // kunci datar (tanpa notasi titik) agar bebas dari ambiguitas
                // penulisan session dot-key; path di-hash supaya pendek & aman.
                $key = $request->user()->id.':'.sha1('/'.$request->path());

                /** @var array<string, string> $logged */
                $logged = session(self::SESSION_KEY, []);

                if (($logged[$key] ?? null) !== now()->format('YmdHi')) {
                    $logged[$key] = now()->format('YmdHi');

                    // simpan hanya 40 kunci terakhir agar sesi tidak membengkak
                    if (count($logged) > 40) {
                        $logged = array_slice($logged, -40, preserve_keys: true);
                    }

                    session([self::SESSION_KEY => $logged]);

                    $path = trim($request->getPathInfo(), '/');

                    AuditLog::record(
                        AuditLog::EVENT_VISIT,
                        null,
                        'Mengakses halaman '.($path === '' ? 'beranda' : $path),
                    );
                }
            }
        } catch (\Throwable $e) {
            // logging tidak boleh mengganggu request utama —
            // tapi kegagalan tetap dicatat ke laravel.log agar mudah diperiksa.
            Log::warning('Gagal mencatat kunjungan halaman: '.$e->getMessage());
        }

        return $response;
    }
}
