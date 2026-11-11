<?php

namespace App\Imports;

use App\Models\AuditLog;
use App\Models\PositionMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import KERTAS KERJA PETA JABATAN (Point 4) dari berkas Excel —
 * format persis "KERTAS KERJA EXCEL.xlsx":
 *
 *   No | Jabatan dan Jenjang | Dukungan Lintas Program | Intervensi Program 5 T |
 *   Kualifikasi Pendidikan | Kompetensi | Kebutuhan | Pemangku jabatan eksisiting |
 *   Kesenjangan Jumlah | Alternatif Penataan/Pemenuhan | Rekomendasi Utama
 *
 * Perilaku:
 *  - Posisi baris header dicari otomatis (boleh baris 1-10; pada berkas asli
 *    berada di baris 2) dan kolom dikenali dari kata kunci judulnya sehingga
 *    tahan salah ketik ("eksisiting" vs "eksisting") maupun urutan kolom.
 *  - Mode GANTI SELURUH DATA: tabel position_maps dikosongkan lalu diisi
 *    ulang sesuai urutan baris berkas (kertas kerja = dokumen tunggal).
 *  - Sel teks multi-baris dibersihkan (baris kosong dibuang) dan disimpan
 *    dengan pemisah baris "\n" agar rapi saat ditampilkan/dieksport.
 */
class PositionMapsImport
{
    /**
     * @var array<string, string> kata kunci judul kolom → field position_maps.
     *             Urutan menentukan prioritas: kata kunci SPESIFIK didahulukan agar
     *             "Pemangku jabatan eksisiting" tidak tertangkap "jabatan".
     */
    private const COLUMN_KEYWORDS = [
        'pemangku' => 'existing_count',       // "Pemangku jabatan eksisiting"
        'eksisiting' => 'existing_count',     // (typo pada berkas asli)
        'eksisting' => 'existing_count',
        'kesenjangan' => 'gap_count',         // "Kesenjangan Jumlah"
        'alternatif' => 'fulfillment_alternative', // "Alternatif Penataan/Pemenuhan"
        'penataan' => 'fulfillment_alternative',
        'rekomendasi' => 'main_recommendation', // "Rekomendasi Utama"
        'dukungan' => 'cross_program',        // "Dukungan Lintas Program"
        'intervensi' => 'program_intervention', // "Intervensi Program 5 T"
        'pendidikan' => 'education_qualification', // "Kualifikasi Pendidikan"
        'kualifikasi' => 'education_qualification',
        'kompetensi' => 'competency',
        'kebutuhan' => 'required_count',
        'jabatan' => 'name',                  // "Jabatan dan Jenjang" (paling umum — terakhir)
    ];

    public int $imported = 0;

    /**
     * Jalankan import. Mengembalikan jumlah baris yang dimuat.
     *
     * @throws \RuntimeException bila kolom "Jabatan dan Jenjang" tidak ditemukan
     */
    public function import(string $path, ?string $originalName = null): int
    {
        $spreadsheet = IOFactory::load($path);
        $imported = 0;
        $pending = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = collect($sheet->toArray(null, true, false, true));
            $map = $this->findHeaderColumns($rows);

            if ($map === null) {
                continue; // sheet tanpa kolom "Jabatan dan Jenjang" → dilewati
            }

            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber <= $map['row']) {
                    continue; // lewati judul & baris header
                }

                $name = $this->cleanText($this->cell($row, $map['columns']['name'] ?? null), 200);

                if ($name === '' || mb_strtolower($name) === 'jabatan dan jenjang') {
                    continue; // baris kosong / pengulangan header
                }

                $pending[] = [
                    'name' => $name,
                    'cross_program' => $this->cleanNullable($this->cell($row, $map['columns']['cross_program'] ?? null)),
                    'program_intervention' => $this->cleanNullable($this->cell($row, $map['columns']['program_intervention'] ?? null)),
                    'education_qualification' => $this->cleanNullable($this->cell($row, $map['columns']['education_qualification'] ?? null)),
                    'competency' => $this->cleanNullable($this->cell($row, $map['columns']['competency'] ?? null)),
                    'required_count' => $this->parseInt($this->cell($row, $map['columns']['required_count'] ?? null)),
                    'existing_count' => $this->parseInt($this->cell($row, $map['columns']['existing_count'] ?? null)),
                    'gap_count' => $this->parseInt($this->cell($row, $map['columns']['gap_count'] ?? null)),
                    'fulfillment_alternative' => $this->cleanNullable($this->cell($row, $map['columns']['fulfillment_alternative'] ?? null)),
                    'main_recommendation' => $this->cleanNullable($this->cell($row, $map['columns']['main_recommendation'] ?? null)),
                    'sort_order' => $imported + 1,
                ];

                $imported++;
            }
        }

        if ($imported === 0) {
            throw new \RuntimeException(
                'Kolom "Jabatan dan Jenjang" tidak ditemukan atau tidak ada baris data. '
                .'Pastikan berkas merupakan Kertas Kerja Peta Jabatan (format 11 kolom).'
            );
        }

        // GANTI SELURUH DATA — atomik: gagal di tengah jalan → data lama tetap utuh
        // (delete() dipakai, bukan truncate(), agar tetap berada dalam transaksi)
        DB::transaction(function () use ($pending): void {
            PositionMap::query()->delete();

            foreach ($pending as $attributes) {
                PositionMap::create($attributes);
            }
        });

        $this->imported = $imported;

        AuditLog::record(AuditLog::EVENT_CREATE, 'peta-jabatan',
            'Import kertas kerja peta jabatan ('.$imported.' baris) — '.basename($originalName ?? $path));

        return $imported;
    }

    /** Ambil nilai mentah sel pada kolom (null bila kolom tidak ada). */
    private function cell($row, ?string $column): mixed
    {
        return $column !== null ? ($row[$column] ?? null) : null;
    }

    /**
     * Cari baris & peta kolom header. Judul tiap sel dinormalisasi lalu
     * dicocokkan ke field melalui kata kunci (tahan salah ketik & urutan).
     */
    private function findHeaderColumns($rows): ?array
    {
        foreach ($rows->take(10) as $rowNumber => $row) {
            $columns = [];

            foreach ($row as $column => $value) {
                $key = mb_strtolower(trim((string) preg_replace('/[^\pL\s\/]+/u', ' ', (string) $value)));
                $key = trim((string) preg_replace('/\s+/u', ' ', $key));

                if ($key === '') {
                    continue;
                }

                foreach (self::COLUMN_KEYWORDS as $needle => $field) {
                    if (str_contains($key, $needle)) {
                        // kata kunci pertama yang cocok menang (spesifik → umum)
                        $columns[$field] ??= $column;

                        break;
                    }
                }
            }

            // baris header kertas kerja harus mengenali banyak kolom sekaligus
            // (minimal 4) agar baris judul dokumen (mis. "KERTAS KERJA PETA
            // JABATAN") yang memuat kata "jabatan" tidak salah terdeteksi
            if (isset($columns['name']) && count($columns) >= 4) {
                return ['row' => $rowNumber, 'columns' => $columns];
            }
        }

        return null;
    }

    /* ================= NORMALISASI NILAI SEL ================= */

    /**
     * Teks: buang tanda petik Excel, rapikan spasi, pecah baris, buang baris
     * kosong, lalu batasi panjang. Sel angka dibaca tanpa notasi ilmiah.
     */
    private function cleanText(mixed $value, int $limit = 65535): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && $value == floor($value) && abs($value) < 1e15) {
            return number_format($value, 0, '', '');
        }

        if (! is_string($value)) {
            return trim((string) $value);
        }

        $lines = collect(preg_split('/\r\n|\r|\n/u', ltrim(trim($value), "'")))
            ->map(fn ($line) => trim((string) preg_replace('/[ \t]+/u', ' ', $line)))
            // perbaiki artefak umum kertas kerja: ".- Berstatus" → "- Berstatus"
            ->map(fn ($line) => preg_replace('/^\.[\s]*(-)/u', '$1', $line) ?? $line)
            ->filter(fn ($line) => $line !== '' && $line !== '-')
            ->values();

        return Str::limit($lines->implode("\n"), $limit, '');
    }

    /** cleanText yang mengembalikan null untuk sel kosong. */
    private function cleanNullable(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === '' ? null : $text;
    }

    /** Angka bulat kecil (jumlah pegawai); sel kosong/selain angka → 0. */
    private function parseInt(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            return max(0, (int) round($value));
        }

        if ((bool) preg_match('/-?\d+/', trim((string) $value), $m)) {
            return (int) $m[0];
        }

        return 0;
    }
}
