<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeePosition;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeder pegawai dari data riil "Data Dashboard Revisi.xlsx" (197 pegawai).
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/employees.json');

        if (! file_exists($path)) {
            $this->command?->warn('File database/data/employees.json tidak ditemukan, seeder dilewati.');

            return;
        }

        $rows = json_decode(file_get_contents($path), true);

        // lookup master
        $statusMap = EmploymentStatus::pluck('id', 'name')->all();
        $statusMap['PPPK Penuh Waktu'] = $statusMap['PPPK Penuh Waktu'] ?? null;
        $rankMap = \App\Models\Rank::pluck('id', 'code')->all();
        $eduMap = \App\Models\EducationLevel::pluck('id', 'code')->all();

        // unit kerja: distribusi ke Bagian di bawah Biro OSDMRB
        $bagian = Unit::where('level', 'ES_III')->orderBy('id')->pluck('id')->all();
        $biro = Unit::where('code', 'OSDMRB')->first();

        // posisi
        $positionMap = Position::pluck('id', 'code')->all();

        foreach ($rows as $i => $row) {
            $name = trim($row['NAMA'] ?? '');
            if (! $name) {
                continue;
            }

            // ---- atribut dasar ----
            $gender = ($row['JENIS KELAMIN'] ?? 'Laki-Laki') === 'Perempuan' ? 'P' : 'L';
            $birthDate = $this->date($row['TANGGAL LAHIR'] ?? null);

            // ---- status ASN ----
            $statusName = match ($row['STATUS'] ?? '') {
                'PNS' => 'PNS',
                'PPPK Penuh Waktu' => 'PPPK Penuh Waktu',
                'PPPK Paruh Waktu' => 'PPPK Paruh Waktu',
                default => null,
            };

            // ---- eselon & level fungsional ----
            $eselonRaw = $row['Level Eselon'] ?? null;
            $eselon = in_array($eselonRaw, ['II', 'III', 'IV']) ? $eselonRaw : null;
            $functionalLevel = $eselon ? null : ($row['Level Fungsional'] ?? $row['Level Jabatan'] ?? null);
            $functionalLevel = in_array($functionalLevel, ['-', 'Fungsional Umum', 'PPPK Fungsional Umum']) ? null : $functionalLevel;

            // ---- posisi jabatan ----
            [$positionCode, $positionName] = $this->resolvePosition($eselon, $functionalLevel, $row['Level Jabatan'] ?? null, $statusName);

            // ---- unit: struktural menempati biro, lainnya disebar ke bagian ----
            $unitId = $eselon
                ? $biro?->id
                : $bagian[$i % count($bagian)];

            // ---- NIP dummy (18 digit) ----
            $nip = $this->generateNip($birthDate, $gender, $i);

            // updateOrCreate: aman bila seeder dijalankan berulang
            $employee = Employee::updateOrCreate(
                ['nip' => $nip],
                [
                    'name' => $name,
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'religion' => $row['AGAMA'] ?? null,
                    'employment_status_id' => $statusMap[$statusName] ?? null,
                    'rank_id' => $rankMap[$row['GOLONGAN'] ?? ''] ?? null,
                    'education_level_id' => $eduMap[$row['LEVEL PENDIDIKAN'] ?? ''] ?? null,
                    'unit_id' => $unitId,
                    'eselon' => $eselon,
                    'functional_level' => $functionalLevel,
                    'position_name' => $positionName,
                    'tmt_jabatan' => $this->date($row['TMT JABATAN'] ?? null),
                    'tmt_golongan' => $this->date($row['TMT GOL'] ?? null),
                    'retirement_date' => $this->date($row['BATAS USIA PENSIUN'] ?? null),
                    'education_1' => $row['PENDIDIKAN TERAKHIR 1'] ?? null,
                    'education_2' => $row['PENDIDIKAN TERAKHIR 2'] ?? null,
                    'education_3' => $row['PENDIDIKAN TERAKHIR 3'] ?? null,
                    'is_active' => true,
                ]
            );

            // ---- riwayat jabatan saat ini ----
            if ($positionCode && isset($positionMap[$positionCode])) {
                EmployeePosition::updateOrCreate(
                    ['employee_id' => $employee->id, 'is_current' => true],
                    [
                        'position_id' => $positionMap[$positionCode],
                        'unit_id' => $unitId,
                        'start_date' => $this->date($row['TMT JABATAN'] ?? null) ?? now()->toDateString(),
                    ]
                );
            }
        }

        $this->command?->info('Seeder pegawai: '.Employee::count().' data.');
    }

    /* ================= HELPERS ================= */

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        // format m/d/Y dari Excel
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $value)) {
            return Carbon::createFromFormat('n/j/Y', $value)?->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolvePosition(?string $eselon, ?string $functional, ?string $levelJabatan, ?string $status): array
    {
        // struktural
        if ($eselon === 'II') {
            return ['STR-KABIRO', 'Kepala Biro OSDMRB (Eselon II)'];
        }
        if ($eselon === 'III') {
            return ['STR-KABAG', 'Kepala Bagian (Eselon III)'];
        }
        if ($eselon === 'IV') {
            return ['STR-KASUBAG', 'Kepala Subbagian (Eselon IV)'];
        }

        // fungsional tertentu
        return match ($functional) {
            'Madya', 'Fungsional Madya' => ['FUN-ANALIS-MADYA', 'Analis SDM Ahli Madya'],
            'Muda', 'Fungsional Muda' => ['FUN-ANALIS-MUDA', 'Analis SDM Ahli Muda'],
            'Pertama', 'Fungsional Pertama' => ['FUN-ANALIS-PERTAMA', 'Analis SDM Ahli Pertama'],
            'Terampil', 'PPPK Terampil' => ['PEL-PPPK-TERAMPIL', 'PPPK Pelaksana Terampil'],
            'Mahir' => ['PEL-PPPK-MAHIR', 'PPPK Pelaksana Mahir'],
            'Penyelia' => ['PEL-PPPK-PENYELIA', 'PPPK Pelaksana Penyelia'],
            'PPPK Umum' => ['PEL-PPPK-UMUM', 'PPPK Fungsional Umum'],
            'PPPK Terampil' => ['PEL-PPPK-TERAMPIL', 'PPPK Pelaksana Terampil'],
            default => ['PEL-PENGELOLA', 'Pengelola Kepegawaian (Fungsional Umum)'],
        };
    }

    private function generateNip(?string $birthDate, string $gender, int $index): string
    {
        $birth = $birthDate ? Carbon::parse($birthDate)->format('Ymd') : '19800101';
        $tmt = '201501'; // TMT CPNS dummy
        $genderDigit = $gender === 'P' ? 2 : 1;

        return $birth.$tmt.$genderDigit.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
    }
}
