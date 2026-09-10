<?php

namespace App\Imports;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import daftar pegawai non ASN (pramubakti, security, cleaning service).
 *
 * Ketiga berkas sumber memiliki format yang tidak beraturan:
 * - Personil PB      : NO | ID PEGAWAI | NAMA | UNIT KERJA ESELON II | UNIT KERJA ESELON I
 * - Security         : NO | NAMA | JABATAN (Koordinator/Pamdal/Chief/Secwan/...)
 * - Cleaning Service : NO | NAMA
 *
 * Baris & kolom header dicari otomatis (bisa di baris 4-9), sehingga berkas
 * asli instansi bisa langsung diunggah dari dashboard tanpa perlu dirapikan.
 *
 * Kegunaan bersama: form import dashboard & command employees:import.
 */
class NonAsnEmployeesImport
{
    public int $created = 0;

    public int $updated = 0;

    /** kategori terdeteksi/terpakai, mis. "Pramubakti" */
    public string $category = 'Non ASN';

    public function __construct(?string $category = null)
    {
        if ($category !== null && $category !== '') {
            $this->category = $category;
        }
    }

    /**
     * Jalankan import. Mengembalikan ringkasan hasil.
     *
     * @param  string  $path          path berkas di disk
     * @param  string|null  $originalName  nama asli berkas (untuk deteksi kategori saat upload web,
     *                                     karena $path hanya berupa berkas temporer php)
     * @return array{created: int, updated: int, category: string}
     */
    public function import(string $path, ?string $originalName = null): array
    {
        if ($this->category === 'Non ASN') {
            $this->category = $this->detectCategory($originalName ?? $path);
        }

        $rows = collect(IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true));

        $headers = $this->findHeaderColumns($rows);

        if (! $headers || $headers['nama'] === null) {
            throw new \RuntimeException('Kolom NAMA tidak ditemukan pada berkas. Pastikan berkas berisi daftar nama pegawai non ASN (header "NAMA").');
        }

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headers['row']) {
                continue; // lewati judul & baris header
            }

            $cells = array_map(fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '', $row);

            $nipSource = $headers['id'] !== null ? ($cells[$headers['id']] ?? null) : null;
            $name = $cells[$headers['nama']] ?? null;
            $jabatan = $headers['jabatan'] !== null ? ($cells[$headers['jabatan']] ?? null) : null;
            $unit = $headers['unit'] !== null ? ($cells[$headers['unit']] ?? null) : null;

            if (! $name || $name === '-' || ! (bool) preg_match("/^[\pL\s.,'\-]+$/u", $name)) {
                continue; // bukan baris nama pegawai
            }

            // lewati judul / nama perusahaan
            $lower = Str::lower($name);
            if (str_contains($lower, 'pt.') || str_contains($lower, 'kementerian')
                || str_contains($lower, 'tanda terima') || str_contains($lower, 'daftar')
                || str_contains($lower, 'nama - nama') || str_contains($lower, 'peserta')) {
                continue;
            }

            $nip = $nipSource && $nipSource !== ''
                ? Str::limit($nipSource, 30, '')
                : null;

            $name = Str::title(mb_strtolower($name));

            $data = [
                'name' => $name,
                'employee_type' => Employee::TYPE_NON_ASN,
                'category' => $this->category,
                'position_name' => $jabatan ?: ($unit ? "Personil {$this->category} - {$unit}" : "Personil {$this->category}"),
                'is_active' => true,
            ];

            // cari pegawai existing: via ID (bila berkas punya kolom ID PEGAWAI),
            // atau via nama + kategori (berkas tanpa ID seperti daftar Security/Cleaning)
            $employee = null;

            if ($nip !== null) {
                $employee = Employee::where('nip', $nip)->first();
            }

            if ($employee === null) {
                $employee = Employee::query()
                    ->where('employee_type', Employee::TYPE_NON_ASN)
                    ->where('category', $this->category)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();
            }

            if ($employee) {
                // jangan menimpa tipe ASN bila NIP bertabrakan
                if ($employee->employee_type === Employee::TYPE_ASN) {
                    continue;
                }

                $employee->update($nip !== null ? $data + ['nip' => $nip] : $data);
                $this->updated++;
            } else {
                Employee::create($data + ['nip' => $nip ?? self::generateCode($this->category, $name)]);
                $this->created++;
            }
        }

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai', "Import pegawai non ASN kategori {$this->category}");

        return ['created' => $this->created, 'updated' => $this->updated, 'category' => $this->category];
    }

    /**
     * Kode ID pegawai non ASN yang ringkas & unik (maks 30 karakter).
     * Contoh: SEC-AFRIYANTO, SEC-AFRIYANTO-2, CS-HENDRA, PB-060.
     */
    public static function generateCode(string $category, string $name): string
    {
        $prefix = match (true) {
            str_contains(mb_strtolower($category), 'security') => 'SEC',
            str_contains(mb_strtolower($category), 'cleaning') => 'CS',
            str_contains(mb_strtolower($category), 'pramubakti') => 'PB',
            default => strtoupper(substr(preg_replace('/[^a-z]/i', '', $category) ?: 'NAS', 0, 3)),
        };

        $words = preg_split('/\s+/', trim($name)) ?: [];
        $slug = Str::upper(Str::slug(implode(' ', array_slice($words, 0, 2)), '-'));
        $slug = Str::limit($slug, 26, '');

        $code = "{$prefix}-{$slug}";
        $i = 1;
        while (Employee::where('nip', $code)->exists()) {
            $suffix = '-'.(++$i);
            $code = $prefix.'-'.Str::limit($slug, 30 - strlen($prefix) - 1 - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    /**
     * Kategori non ASN dari nama berkas.
     */
    public function detectCategory(string $path): string
    {
        $name = mb_strtolower(basename($path));

        return match (true) {
            str_contains($name, 'security') => 'Security',
            str_contains($name, 'pramubakti') || str_contains($name, 'personil pb') => 'Pramubakti',
            str_contains($name, 'cleaning') => 'Cleaning Service',
            default => 'Non ASN',
        };
    }

    /**
     * Cari posisi baris & kolom header (NAMA/JABATAN/ID PEGAWAI/UNIT) dari seluruh baris.
     */
    private function findHeaderColumns($rows): ?array
    {
        foreach ($rows as $rowNumber => $row) {
            $cells = array_map(fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '', $row);

            $normalized = array_map(
                fn ($v) => Str::upper((string) preg_replace('/\s+/', ' ', $v)),
                $cells
            );

            $namaColumn = array_search('NAMA', $normalized, true);

            if ($namaColumn !== false) {
                return [
                    'row' => $rowNumber,
                    'nama' => $namaColumn,
                    'jabatan' => array_search('JABATAN', $normalized, true) ?: null,
                    'id' => array_search('ID PEGAWAI', $normalized, true) ?: null,
                    'unit' => array_search('UNIT KERJA ESELON II', $normalized, true) ?: null,
                ];
            }
        }

        return null;
    }
}
