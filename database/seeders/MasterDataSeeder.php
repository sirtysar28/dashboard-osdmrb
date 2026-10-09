<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\PositionType;
use App\Models\Rank;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder master data — IDEMPOTEN (aman dijalankan berulang kali).
 * Semua entri memakai updateOrCreate berdasarkan kode unik sehingga
 * `php artisan db:seed` tidak akan gagal karena duplikat entry.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // sort_order hanya diisi bila kolomnya sudah ada (migrasi urutan unit
        // 30 Sept 2026) — seeder bisa juga dipanggil migrasi lama yang berjalan
        // sebelum kolom tersebut dibuat
        $hasSortOrder = Schema::hasColumn('units', 'sort_order');
        $unitAttrs = fn (string $name, string $level, ?int $parentId, int $order) => array_filter([
            'name' => $name,
            'level' => $level,
            'parent_id' => $parentId,
            $hasSortOrder ? 'sort_order' : null => $hasSortOrder ? $order : null,
        ], fn ($v) => $v !== null);

        /* ================= UNIT KERJA =================
           Mengacu daftar unit kerja resmi Kementerian Transmigrasi
           (SOTK): Eselon I (Setjen, 2 Ditjen, Itjen) -> Eselon II
           (biro/pusat/direktorat/staf ahli) -> Balai (UPT pelatihan).
           sort_order = urutan resmi lampiran "urutan jabatan.xlsx"
           (Catatan Masukan 30 Sept 2026): Setjen -> Ditjen Ekbang ->
           Ditjen Kawasan -> Itjen; biro/pusat Setjen: PKHM, OSDMRB,
           ULP, KBMN, Hukum, Pusat STK, PSDM, Datin. */

        $kementerian = Unit::updateOrCreate(
            ['code' => 'KEMEN'],
            $unitAttrs('Kementerian Transmigrasi', 'KEMENTERIAN', null, 1),
        );

        $es1 = collect([
            ['SETJEN', 'Sekretariat Jenderal', 10],
            ['DJ-EKBANG', 'Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi', 20],
            ['DJ-KAWASAN', 'Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigrasi', 30],
            ['ITJEN', 'Inspektorat Jenderal', 40],
        ])->mapWithKeys(function (array $row) use ($kementerian, $unitAttrs) {
            [$code, $name, $order] = $row;

            return [$code => Unit::updateOrCreate(
                ['code' => $code],
                $unitAttrs($name, 'ES_I', $kementerian->id, $order),
            )];
        });

        // Eselon II di bawah masing-masing unit Eselon I
        $es2Map = [
            'SETJEN' => [
                ['BIRO-PKHM', 'Biro Perencanaan, Kerja Sama, dan Hubungan Masyarakat', 11],
                ['OSDMRB', 'Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi', 12],
                ['BIRO-ULP', 'Biro Umum dan Layanan Pengadaan', 13],
                ['BIRO-KBMN', 'Biro Keuangan dan Barang Milik Negara', 14],
                ['BIRO-HUKUM', 'Biro Hukum', 15],
                ['PUS-STK', 'Pusat Strategi Kebijakan Transmigrasi', 16],
                ['PUS-PSDM', 'Pusat Pengembangan Sumber Daya Manusia', 17],
                ['PUS-DATIN', 'Pusat Data dan Informasi Transmigrasi', 18],
                ['STAF-AH-PKLH', 'Staf Ahli Bidang Pembangunan, Kemasyarakatan, dan Lingkungan Hidup', 19],
                ['STAF-AH-POLHUK', 'Staf Ahli Bidang Politik dan Hukum Kementerian Transmigrasi', 20],
            ],
            'ITJEN' => [
                ['SET-ITJEN', 'Sekretariat Inspektorat Jenderal', 41],
                ['ITJEN-I', 'Inspektorat I', 42],
                ['ITJEN-II', 'Inspektorat II', 43],
            ],
            'DJ-EKBANG' => [
                ['SET-DJEKBANG', 'Sekretariat Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi', 21],
                ['DIT-PTPE', 'Direktorat Perencanaan Teknis Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi', 22],
                ['DIT-PKET', 'Direktorat Pengembangan Kelembagaan Ekonomi Transmigrasi', 23],
                ['DIT-PPUT', 'Direktorat Pengembangan Produk Unggulan Transmigrasi', 24],
                ['DIT-PPPU', 'Direktorat Promosi dan Pemasaran Produk Unggulan Transmigrasi', 25],
                ['DIT-PMT', 'Direktorat Pemberdayaan Masyarakat Transmigrasi', 26],
            ],
            'DJ-KAWASAN' => [
                ['SET-DJKWSN', 'Sekretariat Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigrasi', 31],
                ['DIT-PPK', 'Direktorat Perencanaan Perwujudan Kawasan Transmigrasi', 32],
                ['DIT-PBKT', 'Direktorat Pembangunan Kawasan Transmigrasi', 33],
                ['DIT-FPPK', 'Direktorat Fasilitasi Penataan Persebaran Penduduk Di Kawasan Transmigrasi', 34],
                ['DIT-PSPTS', 'Direktorat Pengembangan Satuan Permukiman dan Pusat Satuan Kawasan Pengembangan', 35],
                ['DIT-PKT', 'Direktorat Pengembangan Kawasan Transmigrasi', 36],
            ],
        ];

        foreach ($es2Map as $parentCode => $units) {
            foreach ($units as [$code, $name, $order]) {
                Unit::updateOrCreate(
                    ['code' => $code],
                    $unitAttrs($name, 'ES_II', $es1[$parentCode]->id, $order),
                );
            }
        }

        // Bagian (Eselon III) di bawah Biro OSDMRB — struktur internal biro
        $biro = $es1['SETJEN']->children()->where('code', 'OSDMRB')->first()
            ?? Unit::where('code', 'OSDMRB')->first();

        foreach ([
            ['BAG-ORG', 'Bagian Organisasi dan Tata Laksana', 121],
            ['BAG-SDM', 'Bagian Sumber Daya Manusia dan Diklat', 122],
            ['BAG-RB', 'Bagian Reformasi Birokrasi dan Pengelolaan Kinerja', 123],
        ] as [$code, $name, $order]) {
            Unit::updateOrCreate(
                ['code' => $code],
                $unitAttrs($name, 'ES_III', $biro->id, $order),
            );
        }

        // Balai / UPT pelatihan di bawah Sekretariat Jenderal
        // (urutan resmi: Balai Besar Yogyakarta, Pekanbaru, Banjarmasin, Denpasar)
        foreach ([
            'Balai Besar Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Yogyakarta',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Pekanbaru',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Banjarmasin',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Denpasar',
        ] as $i => $name) {
            Unit::updateOrCreate(
                ['code' => 'BALAI-0'.($i + 1)],
                $unitAttrs($name, 'BALAI', $es1['SETJEN']->id, 51 + $i),
            );
        }

        /* ================= PENDIDIKAN ================= */

        foreach ([
            ['S3', 'S3 / Doktor', 1],
            ['S2', 'S2 / Magister', 2],
            ['S1', 'S1 / Sarjana', 3],
            ['D4', 'D4 / Diploma IV', 4],
            ['D3', 'D3 / Diploma III', 5],
            ['D2', 'D2 / Diploma II', 6],
            ['D1', 'D1 / Diploma I', 7],
            ['SMA', 'SMA / Sederajat', 8],
            ['SMP', 'SMP / Sederajat', 9],
            ['SD', 'SD / Sederajat', 10],
        ] as [$code, $name, $order]) {
            EducationLevel::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $order],
            );
        }

        /* ================= GOLONGAN / PANGKAT ================= */

        $ranks = [
            // PNS
            ['I/a', 'Juru Muda', 'I/a', false, 10],
            ['I/b', 'Juru Muda Tk. I', 'I/b', false, 11],
            ['I/c', 'Juru', 'I/c', false, 12],
            ['I/d', 'Juru Tk. I', 'I/d', false, 13],
            ['II/a', 'Pengatur Muda', 'II/a', false, 14],
            ['II/b', 'Pengatur Muda Tk. I', 'II/b', false, 15],
            ['II/c', 'Pengatur', 'II/c', false, 16],
            ['II/d', 'Pengatur Tk. I', 'II/d', false, 17],
            ['III/a', 'Penata Muda', 'III/a', false, 18],
            ['III/b', 'Penata Muda Tk. I', 'III/b', false, 19],
            ['III/c', 'Penata', 'III/c', false, 20],
            ['III/d', 'Penata Tk. I', 'III/d', false, 21],
            ['IV/a', 'Pembina', 'IV/a', false, 22],
            ['IV/b', 'Pembina Tk. I', 'IV/b', false, 23],
            ['IV/c', 'Pembina Utama Muda', 'IV/c', false, 24],
            ['IV/d', 'Pembina Utama Madya', 'IV/d', false, 25],
            ['IV/e', 'Pembina Utama', 'IV/e', false, 26],
            // PPPK (klaster)
            ['I', 'Klaster I', 'I', true, 1],
            ['II', 'Klaster II', 'II', true, 2],
            ['III', 'Klaster III', 'III', true, 3],
            ['V', 'Klaster V', 'V', true, 4],
            ['VII', 'Klaster VII', 'VII', true, 5],
            ['IX', 'Klaster IX', 'IX', true, 6],
            ['XI', 'Klaster XI', 'XI', true, 7],
        ];

        foreach ($ranks as [$code, $name, $group, $pppk, $order]) {
            Rank::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name, 'group_name' => $group,
                    'is_pppk' => $pppk, 'sort_order' => $order,
                ],
            );
        }

        /* ================= STATUS KEPEGAWAIAN ================= */

        foreach ([
            ['ASN', 'ASN'],
            ['CPNS', 'CPNS'],
            ['PPPK_PENUH', 'PPPK Penuh Waktu'],
            ['PPPK_PARUH', 'PPPK Paruh Waktu'],
        ] as [$code, $name]) {
            EmploymentStatus::updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }

        /* ================= JENIS & LEVEL JABATAN =================

           Jenis jabatan ASN:
           - STRUKTURAL : pejabat negatif pengelola — Eselon I s.d. IV
           - FUNGSIONAL : ahli pertama, muda, madya, penyelia, terampil
           - PELAKSANA  : non-eselon & non-fungsional (staf pelaksana) */

        foreach ([
            ['STRUKTURAL', 'Struktural (Eselon I–IV)'],
            ['FUNGSIONAL', 'Fungsional'],
            ['PELAKSANA', 'Pelaksana (Non-Eselon & Non-Fungsional)'],
        ] as [$code, $name]) {
            PositionType::updateOrCreate(
                ['code' => $code],
                ['name' => $name],
            );
        }

        $jobLevels = [
            // struktural
            ['ESELON_I', 'Eselon I', 1],
            ['ESELON_II', 'Eselon II', 2],
            ['ESELON_III', 'Eselon III', 3],
            ['ESELON_IV', 'Eselon IV', 4],
            // fungsional
            ['AHLI_PERTAMA', 'Fungsional Ahli Pertama', 5],
            ['AHLI_MUDA', 'Fungsional Ahli Muda', 6],
            ['AHLI_MADYA', 'Fungsional Ahli Madya', 7],
            ['AHLI_UTAMA', 'Fungsional Ahli Utama', 8],
            ['PENYELIA', 'Fungsional Penyelia', 9],
            ['TERAMPIL', 'Fungsional Terampil', 10],
            // pelaksana: non-eselon & non-fungsional
            ['PELAKSANA', 'Pelaksana (Non-Eselon & Non-Fungsional)', 11],
        ];

        foreach ($jobLevels as [$code, $name, $order]) {
            JobLevel::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $order],
            );
        }

        /* ================= JABATAN ================= */

        $positions = [
            /* ---- Struktural Eselon I ---- */
            ['STR-SEKJEN', 'Sekretaris Jenderal', 'STRUKTURAL', 'ESELON_I'],
            ['STR-DIRJEN', 'Direktur Jenderal', 'STRUKTURAL', 'ESELON_I'],
            ['STR-ITJEN', 'Inspektur Jenderal', 'STRUKTURAL', 'ESELON_I'],
            /* ---- Struktural Eselon II ---- */
            ['STR-DIREKTUR', 'Direktur', 'STRUKTURAL', 'ESELON_II'],
            // "Sekretaris Direktur Jenderal" DIHAPUS — penamaan salah
            // (Catatan 7 Okt 2026); nama resmi: Sekretaris Direktorat Jenderal
            ['STR-KAPUS', 'Kepala Pusat', 'STRUKTURAL', 'ESELON_II'],
            ['STR-KABIRO', 'Kepala Biro', 'STRUKTURAL', 'ESELON_II'],
            ['STR-INSPEKTUR', 'Inspektur', 'STRUKTURAL', 'ESELON_II'],
            ['STR-SETITJEN', 'Sekretaris Inspektur Jenderal', 'STRUKTURAL', 'ESELON_II'],
            ['STR-KABALBES', 'Kepala Balai Besar', 'STRUKTURAL', 'ESELON_II'],
            /* ---- Struktural Eselon III ---- */
            ['STR-KABAG', 'Kepala Bagian', 'STRUKTURAL', 'ESELON_III'],
            ['STR-KABALAI', 'Kepala Balai', 'STRUKTURAL', 'ESELON_III'],
            /* ---- Struktural Eselon IV ---- */
            ['STR-KASUBAG', 'Kepala Subbagian', 'STRUKTURAL', 'ESELON_IV'],
            /* ---- Fungsional ---- */
            ['FUN-ANALIS-MADYA', 'Analis Sumber Daya Manusia Ahli Madya', 'FUNGSIONAL', 'AHLI_MADYA'],
            ['FUN-ANALIS-MUDA', 'Analis Sumber Daya Manusia Ahli Muda', 'FUNGSIONAL', 'AHLI_MUDA'],
            ['FUN-ANALIS-PERTAMA', 'Analis Sumber Daya Manusia Ahli Pertama', 'FUNGSIONAL', 'AHLI_PERTAMA'],
            ['FUN-AUDITOR-MADYA', 'Auditor Ahli Madya', 'FUNGSIONAL', 'AHLI_MADYA'],
            ['FUN-AUDITOR-MUDA', 'Auditor Ahli Muda', 'FUNGSIONAL', 'AHLI_MUDA'],
            ['FUN-PRANATA-MUDA', 'Pranata Komputer Ahli Muda', 'FUNGSIONAL', 'AHLI_MUDA'],
            ['FUN-ARSIPARIS-PERTAMA', 'Arsiparis Ahli Pertama', 'FUNGSIONAL', 'AHLI_PERTAMA'],
            ['FUN-PENYELIA', 'Fungsional Penyelia', 'FUNGSIONAL', 'PENYELIA'],
            ['FUN-TERAMPIL', 'Fungsional Terampil', 'FUNGSIONAL', 'TERAMPIL'],
            /* ---- Pelaksana (non-eselon & non-fungsional) ---- */
            ['PEL-PENGELOLA', 'Pengelola Kepegawaian', 'PELAKSANA', 'PELAKSANA'],
            ['PEL-PPPK-UMUM', 'PPPK Fungsional Umum', 'PELAKSANA', 'PELAKSANA'],
            ['PEL-PPPK-PENYELIA', 'PPPK Pelaksana Penyelia', 'PELAKSANA', 'PENYELIA'],
            ['PEL-PPPK-TERAMPIL', 'PPPK Pelaksana Terampil', 'PELAKSANA', 'TERAMPIL'],
        ];

        $typeIds = PositionType::pluck('id', 'code');
        $levelIds = JobLevel::pluck('id', 'code');

        foreach ($positions as [$code, $name, $typeCode, $levelCode]) {
            Position::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'position_type_id' => $typeIds[$typeCode],
                    'job_level_id' => $levelIds[$levelCode] ?? null,
                ],
            );
        }
    }
}
