<?php

namespace App\Imports;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import daftar pegawai non ASN dari berkas Excel instansi.
 *
 * FORMAT BARU (bank data Non ASN, Okt 2026) — satu baris per pegawai lengkap:
 *   NO. | NAMA LENGKAP | NIK | NO. HP | EMAIL | AGAMA | TEMPAT | TANGGAL LAHIR |
 *   ALAMAT DOMISILI | UNIT PENEMPATAN
 *
 *   - Satu berkas bisa berisi beberapa sheet (Pramubakti / Driver / Teknisi) —
 *     kategori diambil dari judul sheet masing-masing.
 *   - Tanggal lahir bisa berupa sel tanggal Excel, serial Excel (26040),
 *     "24/09/1985", "21-02-1995", maupun "6 Januari 2000" (nama bulan,
 *     salah ketik seperti "Deseember" tetap terbaca).
 *   - NIK bertanda petik satu di depan ('3175...) dibersihkan otomatis.
 *   - "Unit Penempatan" dipetakan ke unit kerja dashboard (segment sebelum
 *     koma, mis. "Biro Keuangan dan Barang Milik Negara, Sekretariat Jenderal"
 *     → biro terkait); yang tidak dikenali jatuh ke Sekretariat Jenderal.
 *
 * FORMAT LAMA tetap didukung (posisi header dicari otomatis, bisa di baris 4-9):
 *   - Personil PB      : NO | ID PEGAWAI | NAMA | UNIT KERJA ESELON II | ...
 *   - Security         : NO | NAMA | JABATAN (Koordinator/Pamdal/Chief/...)
 *   - Cleaning Service : NO | NAMA
 *
 * Kegunaan bersama: form import dashboard & command employees:import.
 */
class NonAsnEmployeesImport
{
    public int $created = 0;

    public int $updated = 0;

    /** kategori terdeteksi/terpakai, mis. "Pramubakti" atau "Pramubakti, Driver" */
    public string $category = 'Non ASN';

    /** @var array<string,int> nama unit (lowercase) => id */
    private array $unitLookup = [];

    /** @var array<string,?int> cache hasil peta "unit penempatan" => unit id */
    private array $unitTextCache = [];

    public function __construct(?string $category = null)
    {
        if ($category !== null && $category !== '') {
            $this->category = $category;
        }
    }

    /**
     * Jalankan import (semua sheet diproses). Mengembalikan ringkasan hasil.
     *
     * @param  string  $path          path berkas di disk
     * @param  string|null  $originalName  nama asli berkas (untuk deteksi kategori saat upload web,
     *                                     karena $path hanya berupa berkas temporer php)
     * @return array{created: int, updated: int, category: string, categories: array<int, string>}
     */
    public function import(string $path, ?string $originalName = null): array
    {
        // kategori manual dari pemilihan form/command menang atas deteksi otomatis
        $manualCategory = $this->category !== 'Non ASN' ? $this->category : null;
        $fileCategory = $manualCategory
            ?? $this->detectCategoryFromText(basename($originalName ?? $path))
            ?? 'Non ASN';

        $spreadsheet = IOFactory::load($path);
        $categories = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            // nilai sel diambil mentah (bukan hasil format) supaya sel tanggal
            // Excel tetap terbaca sebagai serial angka, bukan teks tampilan
            $rows = collect($sheet->toArray(null, true, false, true));

            $headers = $this->findHeaderColumns($rows);

            if (! $headers || $headers['nama'] === null) {
                continue; // sheet tanpa kolom nama → dilewati
            }

            // kategori per sheet: judul sheet (Pramubakti/Driver/Teknisi/...)
            // menang atas kategori dari nama berkas
            $category = $manualCategory
                ?? $this->detectCategoryFromText($sheet->getTitle())
                ?? $fileCategory;

            $this->processSheet($rows, $headers, $category);

            if (! in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        if ($categories === []) {
            throw new \RuntimeException(
                'Kolom NAMA / NAMA LENGKAP tidak ditemukan pada berkas. Pastikan berkas berisi daftar pegawai non ASN.'
            );
        }

        $this->category = implode(', ', $categories);

        AuditLog::record(AuditLog::EVENT_CREATE, 'pegawai', "Import pegawai non ASN kategori {$this->category}");

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'category' => $this->category,
            'categories' => $categories,
        ];
    }

    /**
     * Proses satu sheet: baris per baris → create/update pegawai non ASN.
     *
     * @param  \Illuminate\Support\Collection  $rows
     */
    private function processSheet($rows, array $headers, string $category): void
    {
        // Unit kerja tak dikenal pada format lama → Sekretariat Jenderal
        // (catatan rapat 23-25 Sept 2026: jumlah Non ASN per eselon/balai
        // berbeda-beda ketika dashboard difilter).
        $setjenId = Unit::where('code', 'SETJEN')->value('id')
            ?? Unit::where('name', 'like', 'Sekretariat Jenderal%')->value('id');

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headers['row']) {
                continue; // lewati judul & baris header
            }

            $cells = array_map(fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '', $row);

            $name = $cells[$headers['nama']] ?? null;
            if (! $name || $name === '-' || ! (bool) preg_match("/^[\pL\s.,'()\/\-]+$/u", $name)) {
                continue; // bukan baris nama pegawai
            }

            // lewati judul / nama perusahaan
            $lower = Str::lower($name);
            if (str_contains($lower, 'pt.') || str_contains($lower, 'kementerian')
                || str_contains($lower, 'tanda terima') || str_contains($lower, 'daftar')
                || str_contains($lower, 'nama - nama') || str_contains($lower, 'peserta')) {
                continue;
            }

            $name = Str::title(mb_strtolower($name));

            // ---- kolom format baru (kosong pada format lama) ----
            $nip = $this->normalizeIdentifier($this->cell($row, $headers['id']));
            $phone = $this->normalizePhone($this->cell($row, $headers['phone']));
            $email = $this->normalizeEmail($this->cell($row, $headers['email']));
            $religion = $this->normalizeText($this->cell($row, $headers['agama']), 30);
            $birthPlace = $this->normalizeText($this->cell($row, $headers['tempat']));
            $birthDate = $this->parseDate($this->cell($row, $headers['tanggal']));
            $address = $this->normalizeText($this->cell($row, $headers['alamat']), 1000);

            $jabatan = $headers['jabatan'] !== null ? ($cells[$headers['jabatan']] ?? null) : null;
            $unitText = $headers['unit'] !== null ? ($cells[$headers['unit']] ?? null) : null;
            $unitId = $unitText ? $this->resolveUnitId($unitText) : null;

            $data = [
                'name' => $name,
                'employee_type' => Employee::TYPE_NON_ASN,
                'category' => $category,
                'position_name' => $jabatan ?: "Personil {$category}",
                'is_active' => true,
            ];

            foreach ([
                'phone' => $phone,
                'email' => $email,
                'religion' => $religion,
                'birth_place' => $birthPlace,
                'birth_date' => $birthDate,
                'address' => $address,
            ] as $field => $value) {
                if ($value !== null) {
                    $data[$field] = $value;
                }
            }

            // cari pegawai existing: via NIK/ID (bila berkas punya kolom NIK/ID PEGAWAI),
            // atau via nama + kategori (berkas lama tanpa ID seperti Security/Cleaning)
            $employee = null;

            if ($nip !== null) {
                $employee = Employee::where('nip', $nip)->first();
            }

            if ($employee === null) {
                $employee = Employee::query()
                    ->where('employee_type', Employee::TYPE_NON_ASN)
                    ->where('category', $category)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();
            }

            if ($employee) {
                // jangan menimpa tipe ASN bila NIP bertabrakan
                if ($employee->employee_type === Employee::TYPE_ASN) {
                    continue;
                }

                // berkas baru menyebut unit penempatan → timpa; berkas lama
                // tanpa kolom unit → cukup pastikan tidak kosong (Setjen)
                if ($unitId !== null || ! $employee->unit_id) {
                    $data['unit_id'] = $unitId ?? $setjenId;
                }

                // NIK/ID resmi menimpa kode hasil generateCode
                if ($nip !== null) {
                    $data['nip'] = $nip;
                }

                $employee->update($data);
                $this->updated++;
            } else {
                $data['unit_id'] = $unitId ?? $setjenId;

                Employee::create($data + ['nip' => $nip ?? self::generateCode($category, $name)]);
                $this->created++;
            }
        }
    }

    /** Ambil nilai mentah sel pada kolom (null bila kolom tidak ada). */
    private function cell($row, ?string $column): mixed
    {
        return $column !== null ? ($row[$column] ?? null) : null;
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
     * Kategori non ASN dari nama berkas / judul sheet (null bila tak dikenali).
     */
    public function detectCategoryFromText(string $text): ?string
    {
        $name = mb_strtolower($text);

        return match (true) {
            str_contains($name, 'security') => 'Security',
            str_contains($name, 'pramubakti') || str_contains($name, 'personil pb') => 'Pramubakti',
            str_contains($name, 'cleaning') => 'Cleaning Service',
            str_contains($name, 'driver') => 'Driver',
            str_contains($name, 'teknisi') => 'Teknisi',
            default => null,
        };
    }

    /**
     * Kategori non ASN dari nama berkas (kompatibilitas, "Non ASN" bila tak dikenali).
     */
    public function detectCategory(string $path): string
    {
        return $this->detectCategoryFromText(basename($path)) ?? 'Non ASN';
    }

    /**
     * Cari posisi baris & kolom header dari seluruh baris sheet.
     * Mendukung format baru (NAMA LENGKAP, NIK, NO. HP, ...) dan format lama
     * (NAMA, ID PEGAWAI, JABATAN, UNIT KERJA ESELON II).
     */
    private function findHeaderColumns($rows): ?array
    {
        foreach ($rows as $rowNumber => $row) {
            $cells = array_map(fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '', $row);

            $normalized = [];
            foreach ($cells as $column => $value) {
                $key = Str::upper((string) preg_replace('/[.:,\s]+/u', ' ', $value));
                $key = trim((string) preg_replace('/\s+/u', ' ', $key));
                if ($key !== '') {
                    $normalized[$column] = $key;
                }
            }

            $find = function (array $aliases) use ($normalized) {
                foreach ($aliases as $alias) {
                    $column = array_search($alias, $normalized, true);
                    if ($column !== false) {
                        return $column;
                    }
                }

                return null;
            };

            $namaColumn = $find(['NAMA LENGKAP', 'NAMA']);

            if ($namaColumn !== null) {
                return [
                    'row' => $rowNumber,
                    'nama' => $namaColumn,
                    'id' => $find(['ID PEGAWAI', 'NIK']),
                    'jabatan' => $find(['JABATAN']),
                    'unit' => $find(['UNIT PENEMPATAN', 'UNIT KERJA ESELON II', 'UNIT KERJA']),
                    'phone' => $find(['NO HP', 'NOMOR HP', 'NO TELEPON', 'NOMOR TELEPON', 'TELEPON', 'HP']),
                    'email' => $find(['EMAIL', 'E MAIL']),
                    'agama' => $find(['AGAMA']),
                    'tempat' => $find(['TEMPAT LAHIR', 'TEMPAT']),
                    'tanggal' => $find(['TANGGAL LAHIR', 'TGL LAHIR', 'TANGGAL']),
                    'alamat' => $find(['ALAMAT DOMISILI', 'ALAMAT']),
                ];
            }
        }

        return null;
    }

    /* ================= NORMALISASI NILAI SEL ================= */

    /**
     * Teks biasa: buang tanda petik Excel / spasi berlebih, batasi panjang.
     */
    private function normalizeText(mixed $value, int $limit = 255): ?string
    {
        $text = $this->scalarToString($value);

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    /**
     * NIK / ID pegawai: angka besar dari Excel (float) dibaca tanpa notasi
     * ilmiah, tanda petik di depan ('3175...) dibuang.
     */
    private function normalizeIdentifier(mixed $value): ?string
    {
        if (is_float($value) && $value == floor($value)) {
            $value = number_format($value, 0, '', '');
        }

        $text = $this->scalarToString($value);
        $text = preg_replace('/[\s.\-]/u', '', $text);

        return $text === '' ? null : Str::limit($text, 30, '');
    }

    /**
     * No. HP: buang spasi/tanda baca; pertahankan angka 0 di depan
     * (sel teks) dan angka besar (sel numeric) tanpa notasi ilmiah.
     */
    private function normalizePhone(mixed $value): ?string
    {
        // sel numeric: Excel membuang angka 0 di depan → kembalikan;
        // awalan 62 (kode negara) dibiarkan apa adanya
        if (is_float($value) && $value == floor($value)) {
            $digits = number_format($value, 0, '', '');
            $value = str_starts_with($digits, '62') ? $digits : '0'.$digits;
        }

        $text = $this->scalarToString($value);
        $text = preg_replace('/[\s\-().]/u', '', $text);

        return $text === '' ? null : Str::limit($text, 30, '');
    }

    /**
     * Email: buang seluruh spasi (ada data berspasi akibat salah ketik),
     * kecilkan huruf, validasi format.
     */
    private function normalizeEmail(mixed $value): ?string
    {
        $text = mb_strtolower(preg_replace('/\s+/u', '', $this->scalarToString($value)));

        if ($text === '' || ! (bool) filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $text;
    }

    /**
     * Tanggal lahir dari aneka bentuk sel Excel:
     * - objek DateTime / serial Excel (angka, mis. 26040)
     * - teks "24/09/1985", "21-02-1995", "16.05.2003"
     * - teks bulan Indonesia: "6 Januari 2000", "16 Deseember 2000" (typo tetap lolos)
     * - teks ISO "2000-01-06"
     * Mengembalikan "Y-m-d" atau null bila tidak terbaca.
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // sel tanggal Excel tersimpan sebagai angka serial
        if ((is_int($value) || is_float($value)) && $value >= 10000 && $value <= 60000) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $text = $this->scalarToString($value);
        if ($text === '') {
            return null;
        }

        // dd/mm/yyyy | dd-mm-yyyy | dd.mm.yyyy
        if ((bool) preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $text, $m)
            && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 6 Januari 2000 / 20-Desember-1999 / 04 Agustus1989 (typo minor tetap lolos)
        if ((bool) preg_match('/^(\d{1,2})[\s\-]*([A-Za-z]+)\.?[\s\-]*(\d{4})$/', $text, $m)) {
            $month = $this->mapIndonesianMonth($m[2]);

            if ($month !== null && checkdate($month, (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], $month, (int) $m[1]);
            }

            return null;
        }

        // yyyy-mm-dd (ISO)
        if ((bool) preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /** Nama bulan Indonesia (3 huruf pertama, tahan salah ketik minor) → nomor bulan. */
    private function mapIndonesianMonth(string $name): ?int
    {
        $key = Str::lower(mb_substr(trim($name, '.'), 0, 3));

        return match ($key) {
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'mei' => 5,
            'jun' => 6,
            'jul' => 7,
            'agu', 'ags', 'aug' => 8,
            'sep' => 9,
            'okt', 'oct' => 10,
            'nov' => 11,
            'des', 'dec' => 12,
            default => null,
        };
    }

    /**
     * Nilai sel skalar → teks bersih (float tanpa notasi ilmiah,
     * tanda petik Excel di depan dibuang). Non-skalar → ''.
     */
    private function scalarToString(mixed $value): string
    {
        if ($value === null || $value instanceof \DateTimeInterface) {
            return '';
        }

        if (is_float($value)) {
            return $value == floor($value) && abs($value) < 1e15
                ? number_format($value, 0, '', '')
                : (string) $value;
        }

        if (is_int($value) || is_bool($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim(ltrim(trim($value), "'"));
        }

        return '';
    }

    /**
     * Peta teks "Unit Penempatan" → id unit kerja dashboard.
     * Segment sebelum koma dipakai (mis. "Biro Keuangan dan Barang Milik
     * Negara, Sekretariat Jenderal" → nama biro). Singkatan umum
     * (Ditjen/Setjen) dibentangkan, lalu pencocokan bertahap: persis →
     * awalan nama (nama unit dipotong/lebih panjang di berkas) → mirip
     * (salah ketik). Unit tingkat Kementerian dilewati (kembali ke Setjen
     * via pemanggil).
     */
    private function resolveUnitId(string $raw): ?int
    {
        $segment = trim(explode(',', $raw)[0]);
        $key = mb_strtolower((string) preg_replace('/\s+/u', ' ', $segment));

        // bentangkan singkatan umum: "Sekretariat Ditjen ..." → "Sekretariat Direktorat Jenderal ..."
        $key = str_replace(
            ['direktorat jendral', 'sekertariat', ' ditjen ', 'ditjen '],
            ['direktorat jenderal', 'sekretariat', ' direktorat jenderal ', 'direktorat jenderal '],
            ' '.$key.' '
        );
        $key = trim((string) preg_replace('/\s+/u', ' ', $key));

        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->unitTextCache)) {
            return $this->unitTextCache[$key];
        }

        if ($this->unitLookup === []) {
            $this->unitLookup = Unit::query()
                ->where('level', '!=', 'KEMENTERIAN')
                ->get(['id', 'name'])
                ->mapWithKeys(fn (Unit $unit) => [mb_strtolower(trim($unit->name)) => $unit->id])
                ->all();
        }

        // 1) nama persis
        $id = $this->unitLookup[$key] ?? null;

        // 2) nama unit dipotong / lebih panjang di berkas, mis.
        //    "Pusat Data dan Informasi" → Pusat Data dan Informasi Transmigrasi,
        //    "Pusat ... Sumber Daya Manusia Transmigrasi" → Pusat ... Sumber Daya Manusia
        if ($id === null && mb_strlen($key) >= 15) {
            $bestLength = 0;

            foreach ($this->unitLookup as $name => $unitId) {
                $startsWith = str_starts_with($name, $key) || str_starts_with($key, $name);

                if ($startsWith && mb_strlen($name) > $bestLength) {
                    $bestLength = mb_strlen($name);
                    $id = $unitId;
                }
            }
        }

        // 3) mirip (salah ketik minor, mis. "Transmigarsi", "Kementrian")
        if ($id === null && mb_strlen($key) > 10) {
            $best = null;
            $bestDistance = PHP_INT_MAX;

            foreach ($this->unitLookup as $name => $unitId) {
                if (abs(strlen($name) - strlen($key)) > 2) {
                    continue;
                }

                $distance = levenshtein($name, $key);

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $unitId;
                }
            }

            if ($best !== null && $bestDistance <= 2) {
                $id = $best;
            }
        }

        return $this->unitTextCache[$key] = $id;
    }
}
