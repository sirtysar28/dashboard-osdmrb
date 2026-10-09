<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan Masukan 7 Okt 2026 — butir 3:
 * "Pada bagian CV pegawai, kolom Riwayat Pendidikan, sebaiknya dimulai dari
 *  Pendidikan 1 atau mungkin dituliskan jenjangnya Diploma/Sarjana/Master."
 *
 * Merapikan data lama yang terbalik: jenjang PENDIDIKAN TERENDAH diletakkan di
 * education_1 (Pendidikan 1 = D1–D4/S1), disusul education_2 (S2), lalu
 * education_3 (S3) — sesuai label form "Pendidikan 1 (S1), 2 (S2), 3 (S3)".
 *
 * Jenjang dikenali dari teks (mis. "Magister Administrasi, UGM — S2");
 * isian yang tidak dikenali tetap pada urutannya (stabil & idempoten).
 */
return new class extends Migration
{
    /** Peringkat jenjang: makin kecil makin rendah. 50 = tidak dikenali. */
    private function rank(?string $value): int
    {
        $text = mb_strtolower((string) $value);

        if ($text === '') {
            return 50;
        }

        return match (true) {
            (bool) preg_match('/\bs3\b|\bs-3\b|doktor|doctor|ph\.?\s?d/i', $text) => 40,
            (bool) preg_match('/\bs2\b|\bs-2\b|magister|master|m\.?\s?(sc|si|t|kom|e|ed|hp|farm|kes)|\bmba\b|\bmm\b/i', $text) => 30,
            (bool) preg_match('/\bs1\b|\bs-1\b|sarjana|bachelor|\bb\.?\s?(sc|a|s|eng|kom|e|ed|arch|agr)\b|\bd4\b|diploma\s*(iv|4)/i', $text) => 20,
            (bool) preg_match('/\bd3\b|diploma\s*(iii|3)|ahli\s+madya/i', $text) => 12,
            (bool) preg_match('/\bd2\b|diploma\s*(ii|2)/i', $text) => 11,
            (bool) preg_match('/\bd1\b|diploma\s*(i|1)\b/i', $text) => 10,
            (bool) preg_match('/\bsma\b|\bsmk\b|\bsmac?\b|slta|sekolah\s+menengah/i', $text) => 5,
            default => 50,
        };
    }

    public function up(): void
    {
        if (! Schema::hasTable('employees')
            || ! Schema::hasColumn('employees', 'education_1')) {
            return;
        }

        DB::table('employees')
            ->where(function ($q) {
                $q->whereNotNull('education_1')
                    ->orWhereNotNull('education_2')
                    ->orWhereNotNull('education_3');
            })
            ->orderBy('id')
            ->chunkById(500, function ($employees) {
                foreach ($employees as $employee) {
                    $entries = [
                        ['rank' => $this->rank($employee->education_1), 'idx' => 1, 'value' => $employee->education_1],
                        ['rank' => $this->rank($employee->education_2), 'idx' => 2, 'value' => $employee->education_2],
                        ['rank' => $this->rank($employee->education_3), 'idx' => 3, 'value' => $employee->education_3],
                    ];

                    // urutkan peringkat jenjang (stabil: peringkat sama pertahankan urutan field)
                    usort($entries, fn ($a, $b) => $a['rank'] <=> $b['rank'] ?: $a['idx'] <=> $b['idx']);

                    $sorted = array_column($entries, 'value');

                    // tulis hanya bila urutannya berubah
                    if ($sorted !== [$employee->education_1, $employee->education_2, $employee->education_3]) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update([
                                'education_1' => $sorted[0],
                                'education_2' => $sorted[1],
                                'education_3' => $sorted[2],
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // pengurutan tidak dapat dibalik — data lama tidak dipulihkan
    }
};
