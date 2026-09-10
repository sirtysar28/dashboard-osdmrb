<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveLoan;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        /* ================= KLASIFIKASI ARSIP (mengacu klasifikasi permukaan ANRI) ================= */

        $categories = [
            ['000', 'Umum', 'Arsip umum ketatausahaan'],
            ['400', 'Organisasi dan Tata Laksana', 'Struktur organisasi, nomenklatur, UPT'],
            ['420', 'Kepegawaian', 'Arsip kepegawaian pegawai dan PPPK'],
            ['430', 'Keuangan', 'Dokumen anggaran, belanja, SPJ'],
            ['800', 'Persuratan', 'Surat masuk, surat keluar, nota dinas'],
            ['900', 'Laporan', 'Laporan kinerja, laporan triwulan, tahunan'],
        ];

        foreach ($categories as [$code, $name, $description]) {
            ArchiveCategory::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $description],
            );
        }

        $cat = ArchiveCategory::pluck('id', 'code');
        $employees = Employee::inRandomOrder()->limit(8)->get();
        $admin = \App\Models\User::where('email', 'admin@osdmrb.go.id')->first();

        /* ================= DOKUMEN ARSIP CONTOH ================= */

        $archives = [
            ['ARS/2024/0001', 'SK Pengangkatan Kepala Biro OSDMRB', 'SK', 'SK', '2024-02-01', 'PERMANEN', 'Ruang A / Rak 1 / Boks 01', '000', null],
            ['ARS/2024/0002', 'SK Kenaikan Pangkat Periode April 2024 (Berkas 12 Pegawai)', 'SK', 'SK', '2024-04-01', 'AKTIF', 'Ruang A / Rak 1 / Boks 02', '420', null],
            ['ARS/2024/0003', 'Laporan Kinerja Biro OSDMRB Tahun 2023', 'LAPORAN', 'LAPORAN', '2024-01-31', 'PERMANEN', 'Ruang B / Rak 2 / Boks 05', '900', null],
            ['ARS/2025/0004', 'Dokumen Kontrak PPPK Penuh Waktu Angkatan 2024', 'KONTRAK', 'KONTRAK', '2025-01-15', 'AKTIF', 'Ruang A / Rak 2 / Boks 01', '420', null],
            ['ARS/2025/0005', 'Surat Masuk Undangan Rakornas Reformasi Birokrasi', 'SURAT_MASUK', 'SURAT_MASUK', '2025-03-10', 'INAKTIF', 'Ruang B / Rak 1 / Boks 03', '800', null],
            ['ARS/2025/0006', 'Laporan Triwulan I Reformasi Birokrasi 2025', 'LAPORAN', 'LAPORAN', '2025-04-05', 'DINILAI_KEMBALI', 'Ruang B / Rak 2 / Boks 06', '900', null],
            ['ARS/2025/0007', 'SK Pembentukan Tim Reformasi Birokrasi', 'SK', 'SK', '2025-05-20', 'PERMANEN', 'Ruang A / Rak 1 / Boks 03', '400', null],
            ['ARS/2025/0008', 'Nota Dinas Usulan Struktur Organisasi Balai', 'SURAT_KELUAR', 'LAINNYA', '2025-06-11', 'INAKTIF', 'Ruang B / Rak 1 / Boks 07', '400', null],
        ];

        foreach ($archives as $i => [$number, $title, $type, $typeValue, $date, $retention, $location, $category, $_]) {
            Archive::updateOrCreate(
                ['archive_number' => $number],
                [
                    'title' => $title,
                    'description' => 'Dokumen arsip '.$title,
                    'archive_category_id' => $cat[$category],
                    'employee_id' => $i < 4 ? $employees[$i % $employees->count()]->id : null,
                    'unit_id' => null,
                    'type' => $typeValue,
                    'document_date' => $date,
                    'year' => (int) substr($date, 0, 4),
                    'retention' => $retention,
                    'retention_years' => $retention === 'PERMANEN' ? null : 5,
                    'retention_until' => $retention === 'PERMANEN' ? null : now()->addYears(3),
                    'physical_location' => $location,
                    'status' => 'TERSEDIA',
                    'visibility' => $i === 3 ? 'INTERNAL' : 'PUBLIK', // kontrak PPPK internal
                    'uploaded_by' => $admin?->id,
                ]
            );
        }

        /* ================= CONTOH PEMINJAMAN ================= */

        // lewati bila contoh peminjaman sudah pernah dibuat (seeder idempoten)
        if (ArchiveLoan::exists()) {
            $this->command?->info('Contoh peminjaman sudah ada, dilewati.');

            return;
        }

        $loanable = Archive::where('status', 'TERSEDIA')->get();

        // 1) menunggu persetujuan
        ArchiveLoan::create([
            'archive_id' => $loanable[0]->id,
            'employee_id' => $employees[0]->id,
            'purpose' => 'Keperluan penyusunan laporan evaluasi kinerja biro.',
            'loan_date' => now()->addDays(1)->toDateString(),
            'due_date' => now()->addDays(8)->toDateString(),
            'status' => ArchiveLoan::STATUS_PENDING,
        ]);

        // 2) sedang dipinjam (arsip jadi DIPINJAM)
        $approved = ArchiveLoan::create([
            'archive_id' => $loanable[1]->id,
            'employee_id' => $employees[1]->id,
            'purpose' => 'Verifikasi data kenaikan pangkat untuk penyusunan SK.',
            'loan_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'status' => ArchiveLoan::STATUS_APPROVED,
            'handled_by' => $admin?->id,
        ]);
        $approved->archive->update(['status' => 'DIPINJAM']);

        // 3) sudah dikembalikan
        $returned = ArchiveLoan::create([
            'archive_id' => $loanable[2]->id,
            'employee_id' => $employees[2]->id,
            'purpose' => 'Rekapitulasi laporan triwulan.',
            'loan_date' => now()->subDays(14)->toDateString(),
            'due_date' => now()->subDays(7)->toDateString(),
            'returned_at' => now()->subDays(8),
            'status' => ArchiveLoan::STATUS_RETURNED,
            'handled_by' => $admin?->id,
        ]);
        $returned->archive->update(['status' => 'TERSEDIA']);
    }
}
