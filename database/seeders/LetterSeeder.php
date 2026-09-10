<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterLog;
use App\Models\LetterType;
use Illuminate\Database\Seeder;

class LetterSeeder extends Seeder
{
    public function run(): void
    {
        /* ================= JENIS SURAT ================= */

        $types = [
            ['ST', 'Surat Tugas', '{no}/ST/OSDMRB/{romawi}/{tahun}'],
            ['SK', 'Surat Keterangan', '{no}/SK/OSDMRB/{romawi}/{tahun}'],
            ['SKK', 'Surat Keterangan Kerja', '{no}/SKK/OSDMRB/{romawi}/{tahun}'],
            ['SR', 'Surat Rekomendasi', '{no}/SR/OSDMRB/{romawi}/{tahun}'],
            ['SIK', 'Surat Izin Kegiatan', '{no}/SIK/OSDMRB/{romawi}/{tahun}'],
            ['SKP', 'Surat Keterangan Penghasilan', '{no}/SKP/OSDMRB/{romawi}/{tahun}'],
        ];

        foreach ($types as [$code, $name, $format]) {
            LetterType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'code_format' => $format],
            );
        }

        /* ================= CONTOH PENGAJUAN ================= */

        // lewati bila contoh surat sudah pernah dibuat (seeder idempoten)
        if (Letter::exists()) {
            $this->command?->info('Contoh surat sudah ada, Seeder surat dilewati.');

            return;
        }

        $admin = \App\Models\User::where('email', 'admin@osdmrb.go.id')->first();
        $employees = Employee::orderBy('id')->limit(12)->get();
        $typeIds = LetterType::pluck('id', 'code');

        $samples = [
            ['subject' => 'Surat Tugas Rapat Koordinasi Reformasi Birokrasi', 'type' => 'ST',
             'purpose' => 'Untuk mengikuti rapat koordinasi reformasi birokrasi tingkat nasional.',
             'meta' => ['place' => 'Jakarta', 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(12)->toDateString()],
             'status' => Letter::STATUS_APPROVED],
            ['subject' => 'Surat Keterangan Masih Bekerja', 'type' => 'SKK',
             'purpose' => 'Sebagai kelengkapan pengajuan kredit bank.', 'meta' => [], 'status' => Letter::STATUS_APPROVED],
            ['subject' => 'Surat Keterangan Penghasilan', 'type' => 'SKP',
             'purpose' => 'Untuk keperluan pendaftaran beasiswa anak.', 'meta' => [], 'status' => Letter::STATUS_VERIFIED],
            ['subject' => 'Surat Tugas Diklat Pimpinan', 'type' => 'ST',
             'purpose' => 'Mengikuti diklat pimpinan tingkat pratama angkatan IV.',
             'meta' => ['place' => 'Bandung', 'start_date' => now()->addMonth()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()],
             'status' => Letter::STATUS_PENDING],
            ['subject' => 'Surat Rekomendasi Penggunaan Ruang Rapat', 'type' => 'SR',
             'purpose' => 'Kegiatan sosialisasi sistem dashboard kepegawaian.', 'meta' => ['place' => 'Gedung A Lt. 3'], 'status' => Letter::STATUS_PENDING],
            ['subject' => 'Surat Izin Kegiatan Sosialisasi Dashboard OSDMRB', 'type' => 'SIK',
             'purpose' => 'Sosialisasi aplikasi Dashboard Biro OSDMRB kepada seluruh unit.', 'meta' => ['place' => 'Ruang Rapat Biro'], 'status' => Letter::STATUS_REJECTED],
        ];

        foreach ($samples as $i => $sample) {
            $employee = $employees[$i % $employees->count()];
            $status = $sample['status'];

            $letter = Letter::create([
                'letter_type_id' => $typeIds[$sample['type']],
                'employee_id' => $employee->id,
                'subject' => $sample['subject'],
                'purpose' => $sample['purpose'],
                'letter_date' => now()->subDays($i)->toDateString(),
                'meta' => $sample['meta'],
                'status' => $status,
                'number' => $status === Letter::STATUS_APPROVED
                    ? str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)."/{$sample['type']}/OSDMRB/".['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) now()->format('n')].'/'.now()->format('Y')
                    : null,
                'note' => $status === Letter::STATUS_REJECTED ? 'Mohon lengkapi lampiran undangan kegiatan.' : null,
            ]);

            // log workflow
            LetterLog::create([
                'letter_id' => $letter->id, 'user_id' => $admin?->id,
                'action' => 'SUBMIT', 'to_status' => Letter::STATUS_PENDING,
                'note' => 'Pengajuan surat oleh pegawai.',
                'created_at' => now()->subDays($i + 1),
            ]);

            if (in_array($status, [Letter::STATUS_VERIFIED, Letter::STATUS_APPROVED, Letter::STATUS_REJECTED])) {
                LetterLog::create([
                    'letter_id' => $letter->id, 'user_id' => $admin?->id,
                    'action' => 'VERIFY', 'from_status' => Letter::STATUS_PENDING,
                    'to_status' => Letter::STATUS_VERIFIED, 'created_at' => now()->subDays($i),
                ]);
            }

            if ($status === Letter::STATUS_APPROVED) {
                LetterLog::create([
                    'letter_id' => $letter->id, 'user_id' => $admin?->id,
                    'action' => 'APPROVE', 'from_status' => Letter::STATUS_VERIFIED,
                    'to_status' => Letter::STATUS_APPROVED, 'created_at' => now()->subDays($i),
                ]);
            }

            if ($status === Letter::STATUS_REJECTED) {
                LetterLog::create([
                    'letter_id' => $letter->id, 'user_id' => $admin?->id,
                    'action' => 'REJECT', 'from_status' => Letter::STATUS_PENDING,
                    'to_status' => Letter::STATUS_REJECTED,
                    'note' => 'Mohon lengkapi lampiran undangan kegiatan.',
                ]);
            }
        }
    }
}
