<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Baris KERTAS KERJA PETA JABATAN (Point 4) — satu baris per
 * "Jabatan dan Jenjang" mengikuti format berkas KERTAS KERJA EXCEL:
 *
 *   No | Jabatan dan Jenjang | Dukungan Lintas Program | Intervensi Program 5 T |
 *   Kualifikasi Pendidikan | Kompetensi | Kebutuhan | Pemangku jabatan eksisting |
 *   Kesenjangan Jumlah | Alternatif Penataan/Pemenuhan | Rekomendasi Utama
 *
 * Kolom angka "Pemangku Jabatan Eksisting" & "Kesenjangan Jumlah" pada
 * halaman dashboard dihitung langsung dari data pegawai aktif; angka pada
 * tabel ini hanyalah snapshot saat import (fallback bila jabatan belum
 * ada pemangkunya di data pegawai).
 */
class PositionMap extends Model
{
    /**
     * Jenjang fungsional yang dilepas dari akhir nama jabatan untuk
     * mendapatkan JENIS jabatan (gabungan kata dicek lebih dulu).
     */
    public const JENJANG = [
        'Fungsional Ahli Utama', 'Fungsional Ahli Madya', 'Fungsional Ahli Muda', 'Fungsional Ahli Pertama',
        'Ahli Utama', 'Ahli Madya', 'Ahli Muda', 'Ahli Pertama',
        'Utama', 'Madya', 'Muda', 'Pertama', 'Penyelia', 'Mahir', 'Terampil', 'Pemula',
    ];

    protected $fillable = [
        'name',
        'cross_program',
        'program_intervention',
        'education_qualification',
        'competency',
        'required_count',
        'existing_count',
        'gap_count',
        'fulfillment_alternative',
        'main_recommendation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required_count' => 'integer',
            'existing_count' => 'integer',
            'gap_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Pisahkan nama lengkap menjadi [JENIS jabatan, JENJANG]:
     * "Analis Kepegawaian Ahli Muda" → ["Analis Kepegawaian", "Ahli Muda"].
     * Nama tanpa jenjang ("Penelaah Teknis Kebijakan") dikembalikan apa adanya.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitJenjang(string $positionName): array
    {
        $jenis = trim((string) preg_replace('/\s+/u', ' ', $positionName));

        foreach (self::JENJANG as $level) {
            $pattern = '/\s+'.preg_quote($level, '/').'$/iu';

            if (preg_match($pattern, $jenis)) {
                $stripped = trim((string) preg_replace($pattern, '', $jenis));

                if ($stripped !== '') {
                    return [$stripped, $level];
                }
            }
        }

        return [$jenis, null];
    }

    /** JENIS jabatan (tanpa jenjang), mis. "Analis Kepegawaian". */
    public function jenis(): string
    {
        return self::splitJenjang($this->name)[0];
    }

    /** JENJANG jabatan (null bila tidak ada), mis. "Ahli Muda". */
    public function jenjang(): ?string
    {
        return self::splitJenjang($this->name)[1];
    }
}
