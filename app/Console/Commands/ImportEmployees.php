<?php

namespace App\Console\Commands;

use App\Imports\EmployeesImport;
use App\Imports\NonAsnEmployeesImport;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import data pegawai dari berkas Excel instansi:
 *
 * 1. ASN     : format "Data Dashboard.xlsx"
 *              php artisan employees:import "Data Dashboard.xlsx"
 *
 * 2. NON ASN : format daftar Security / Pramubakti / Cleaning Service
 *              php artisan employees:import "file.xlsx" --type=nonasn
 *              (kategori dideteksi otomatis dari nama file bila tidak diberikan)
 *
 * Kedua jalur juga tersedia dari dashboard admin (menu Data Pegawai / Pegawai Non ASN).
 */
class ImportEmployees extends Command
{
    protected $signature = 'employees:import
                            {file : Path berkas Excel (.xlsx)}
                            {--type=auto : asn|nonasn|auto (deteksi otomatis)}
                            {--category= : Kategori pegawai non ASN (mis. Security, Pramubakti, Cleaning Service)}';

    protected $description = 'Import data pegawai ASN / Non ASN dari berkas Excel instansi';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $type = (string) $this->option('type');
        $category = $this->option('category');

        // ---- deteksi jenis berkas ----
        if ($type === 'auto') {
            $basename = mb_strtolower(basename($path));

            $type = str_contains($basename, 'non asn') || str_contains($basename, 'non-asn')
                || str_contains($basename, 'security') || str_contains($basename, 'pramubakti')
                || str_contains($basename, 'personil pb') || str_contains($basename, 'cleaning')
                ? 'nonasn' : 'asn';
        }

        if ($type === 'nonasn') {
            return $this->importNonAsn($path, $category ?: null);
        }

        $import = new EmployeesImport(createUnits: true);

        $this->info('Mengimpor data pegawai ASN...');
        $this->newLine();

        // hitung sel formula (mis. KENAIKAN PANGKAT = EDATE) menjadi nilai murni
        $resolved = \App\Support\ExcelFormulaResolver::resolve($path);

        Excel::import($import, $resolved);

        @unlink($resolved);

        $this->info("Selesai. Baru: {$import->created}, diperbarui: {$import->updated}.");

        foreach (array_slice($import->errors, 0, 15) as $error) {
            $this->warn('  - '.$error);
        }

        if (count($import->errors) > 15) {
            $this->warn('  ... dan '.(count($import->errors) - 15).' baris lainnya dilewati.');
        }

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai', 'Import massal data pegawai ASN via command');

        return self::SUCCESS;
    }

    /**
     * Import non ASN — memakai parser bersama NonAsnEmployeesImport
     * (sama dengan tombol "Import Excel" di halaman Pegawai Non ASN).
     */
    private function importNonAsn(string $path, ?string $category): int
    {
        $import = new NonAsnEmployeesImport($category);

        $this->info('Mengimpor pegawai non ASN'.($category ? " kategori \"{$category}\"" : '').'...');

        try {
            $result = $import->import($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Selesai (kategori {$result['category']}). Baru: {$result['created']}, diperbarui: {$result['updated']}.");

        return self::SUCCESS;
    }
}
