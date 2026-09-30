<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\PositionType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sinkronisasi master jabatan (positions) dari DATA PEGAWAI — catatan 28 Sept 2026:
 *
 * - Menu Analis Jabatan Fungsional sebelumnya hanya mengenali 10 jenis jabatan
 *   fungsional yang ada di master. Service ini memindahkan SEMUA nama jabatan
 *   (struktural & fungsional) yang sudah masuk di data pegawai ke master jabatan
 *   sehingga analisis mengidentifikasi seluruh jabatan.
 * - Menu Analis Jabatan Struktural sebelumnya selalu "Kosong" karena pemangku
 *   jabatan dihitung dari relasi riwayat jabatan (employee_positions) yang belum
 *   terisi. Service ini juga membuat riwayat jabatan aktif bagi pegawai yang
 *   belum punya, sehingga tiap jabatan menampilkan pimpinannya.
 *
 * Idempoten: aman dijalankan berulang (migrasi & setiap kali menu analisis dibuka).
 */
class JabatanSyncService
{
    /* ================= HELPER KLASIFIKASI ================= */

    /** Normalisasi nama jabatan menjadi kunci pembanding (upper + spasi rapat). */
    public static function normalize(?string $name): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', ' ', trim((string) $name)));
    }

    /** Ambil romawi eselon (I–IV) dari nilai apa pun, mis. "II", "II/A", "Eselon II". */
    public static function romanEselon(?string $eselon): ?string
    {
        if (! preg_match('/\b(I{1,3}|IV)\b/', strtoupper((string) $eselon), $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * Apakah jabatan termasuk FUNGSIONAL TERTENTU (bukan struktural/pelaksana)?
     * Mengikuti aturan dashboard: jenjang fungsional terisi (selain "…Umum")
     * ATAU nama jabatan memuat penanda fungsional tertentu.
     */
    public static function looksFungsional(?string $positionName, ?string $functionalLevel): bool
    {
        $level = mb_strtolower(trim((string) $functionalLevel));

        if ($level !== '' && ! str_contains($level, 'umum')) {
            return true;
        }

        return (bool) preg_match(
            '/fungsional\s+(?:ahli|terampil|penyelia)|ahli\s+(?:utama|madya|muda|pertama)|\bpenyelia\b|\bterampil\b/i',
            (string) $positionName
        );
    }

    /** Kode job level (jenjang) dari eselon / jenjang fungsional / nama jabatan. */
    public static function jobLevelCode(?string $eselon, ?string $functionalLevel, ?string $positionName): ?string
    {
        if ($roman = self::romanEselon($eselon)) {
            return 'ESELON_'.$roman;
        }

        $text = mb_strtolower(trim(($functionalLevel ?: '').' '.($positionName ?: '')));

        return match (true) {
            str_contains($text, 'utama')    => 'AHLI_UTAMA',
            str_contains($text, 'madya')    => 'AHLI_MADYA',
            str_contains($text, 'muda')     => 'AHLI_MUDA',
            str_contains($text, 'pertama')  => 'AHLI_PERTAMA',
            str_contains($text, 'penyelia') => 'PENYELIA',
            str_contains($text, 'terampil') => 'TERAMPIL',
            default => null,
        };
    }

    /* ================= SINKRONISASI ================= */

    /**
     * Pindahkan semua nama jabatan struktural & fungsional dari data pegawai
     * aktif ke master positions, lalu isi riwayat jabatan aktif bagi pegawai
     * yang belum punya. Mengembalikan jumlah jabatan baru & relasi baru.
     */
    public static function syncFromEmployees(): array
    {
        $result = ['created' => 0, 'linked' => 0];

        if (! Schema::hasTable('positions') || ! Schema::hasTable('employee_positions')) {
            return $result;
        }

        $types = PositionType::query()->whereIn('code', ['STRUKTURAL', 'FUNGSIONAL'])->get()->keyBy('code');
        if (! $types->has('STRUKTURAL') || ! $types->has('FUNGSIONAL')) {
            return $result;
        }

        $levels = JobLevel::query()->get()->keyBy('code');

        $employees = Employee::query()
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->whereNotNull('position_name')
            ->where('position_name', '!=', '')
            ->get(['id', 'unit_id', 'position_name', 'eselon', 'functional_level', 'tmt_jabatan']);

        if ($employees->isEmpty()) {
            return $result;
        }

        // indeks master yang ada: berdasar nama (normal) & kode
        $byName = Position::query()->get()->keyBy(fn ($p) => self::normalize($p->name));
        $codes = Position::query()->pluck('code')->flip();

        // pegawai yang sudah punya riwayat jabatan aktif
        $hasCurrent = EmployeePosition::query()->where('is_current', true)
            ->distinct()->pluck('employee_id')->flip();

        foreach ($employees as $employee) {
            $isStruktural = self::romanEselon($employee->eselon) !== null;
            $isFungsional = ! $isStruktural
                && self::looksFungsional($employee->position_name, $employee->functional_level);

            if (! $isStruktural && ! $isFungsional) {
                continue; // pelaksana / fungsional umum — bukan cakupan sinkronisasi ini
            }

            $key = self::normalize($employee->position_name);
            if ($key === '') {
                continue;
            }

            $position = $byName->get($key);

            if (! $position) {
                $code = self::uniqueCode($key, $codes);

                $position = Position::create([
                    'position_type_id' => $types[$isStruktural ? 'STRUKTURAL' : 'FUNGSIONAL']->id,
                    'job_level_id' => $levels->get(
                        self::jobLevelCode($employee->eselon, $employee->functional_level, $employee->position_name)
                    )?->id,
                    'code' => $code,
                    'name' => trim((string) $employee->position_name),
                    'description' => 'Otomatis dari data pegawai (sinkronisasi 28 Sept 2026).',
                ]);

                $byName->put($key, $position);
                $codes->put($code, $codes->count());
                $result['created']++;
            } elseif (! $position->job_level_id) {
                // lengkapi jenjang master lama yang kosong
                $levelId = $levels->get(
                    self::jobLevelCode($employee->eselon, $employee->functional_level, $employee->position_name)
                )?->id;

                if ($levelId) {
                    $position->forceFill(['job_level_id' => $levelId])->save();
                }
            }

            // riwayat jabatan aktif untuk pegawai yang belum punya sama sekali
            if (! $hasCurrent->has($employee->id)) {
                EmployeePosition::create([
                    'employee_id' => $employee->id,
                    'position_id' => $position->id,
                    'unit_id' => $employee->unit_id,
                    'start_date' => $employee->tmt_jabatan?->toDateString() ?? now()->toDateString(),
                    'is_current' => true,
                ]);
                $hasCurrent->put($employee->id, true);
                $result['linked']++;
            }
        }

        return $result;
    }

    /** Kode jabatan unik dari nama, mis. "Analis Kepegawaian Ahli Muda" → JAB-ANALIS-KEPEGAWAI-AHLI-MUDA. */
    private static function uniqueCode(string $normalized, $taken): string
    {
        $base = 'JAB-'.Str::upper(Str::slug(mb_strtolower($normalized), '-'));
        $base = substr($base, 0, 90);
        $code = $base;

        for ($i = 2; $taken->has($code); $i++) {
            $code = substr($base, 0, 90 - strlen((string) $i)).'-'.$i;
        }

        return $code;
    }
}
