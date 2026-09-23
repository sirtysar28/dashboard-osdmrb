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

/**
 * Seeder master data — IDEMPOTEN (aman dijalankan berulang kali).
 * Semua entri memakai updateOrCreate berdasarkan kode unik sehingga
 * `php artisan db:seed` tidak akan gagal karena duplikat entry.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        /* ================= UNIT KERJA =================
           Mengacu daftar unit kerja resmi Kementerian Transmigrasi
           (SOTK): Eselon I (Setjen, Itjen, 2 Ditjen) -> Eselon II
           (biro/pusat/direktorat/staf ahli) -> Balai (UPT pelatihan). */

        $kementerian = Unit::updateOrCreate(
            ['code' => 'KEMEN'],
            ['name' => 'Kementerian Transmigrasi', 'level' => 'KEMENTERIAN'],
        );

        $es1 = collect([
            ['SETJEN', 'Sekretariat Jenderal'],
            ['ITJEN', 'Inspektorat Jenderal'],
            ['DJ-EKBANG', 'Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi'],
            ['DJ-KAWASAN', 'Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigrasi'],
        ])->mapWithKeys(function (array $row) use ($kementerian) {
            [$code, $name] = $row;

            return [$code => Unit::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'level' => 'ES_I', 'parent_id' => $kementerian->id],
            )];
        });

        // Eselon II di bawah masing-masing unit Eselon I
        $es2Map = [
            'SETJEN' => [
                ['BIRO-PKHM', 'Biro Perencanaan, Kerja Sama, dan Hubungan Masyarakat'],
                ['BIRO-KBMN', 'Biro Keuangan dan Barang Milik Negara'],
                ['OSDMRB', 'Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi'],
                ['BIRO-HUKUM', 'Biro Hukum'],
                ['BIRO-ULP', 'Biro Umum dan Layanan Pengadaan'],
                ['PUS-STK', 'Pusat Strategi Kebijakan Transmigrasi'],
                ['PUS-DATIN', 'Pusat Data dan Informasi Transmigrasi'],
                ['PUS-PSDM', 'Pusat Pengembangan Sumber Daya Manusia'],
                ['STAF-AH-PKLH', 'Staf Ahli Bidang Pembangunan, Kemasyarakatan, dan Lingkungan Hidup'],
                ['STAF-AH-POLHUK', 'Staf Ahli Bidang Politik dan Hukum Kementerian Transmigrasi'],
            ],
            'ITJEN' => [
                ['SET-ITJEN', 'Sekretariat Inspektorat Jenderal'],
                ['ITJEN-I', 'Inspektorat I'],
                ['ITJEN-II', 'Inspektorat II'],
            ],
            'DJ-EKBANG' => [
                ['SET-DJEKBANG', 'Sekretariat Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi'],
                ['DIT-PTPE', 'Direktorat Perencanaan Teknis Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi'],
                ['DIT-PKET', 'Direktorat Pengembangan Kelembagaan Ekonomi Transmigrasi'],
                ['DIT-PPUT', 'Direktorat Pengembangan Produk Unggulan Transmigrasi'],
                ['DIT-PPPU', 'Direktorat Promosi dan Pemasaran Produk Unggulan Transmigrasi'],
                ['DIT-PMT', 'Direktorat Pemberdayaan Masyarakat Transmigrasi'],
            ],
            'DJ-KAWASAN' => [
                ['SET-DJKWSN', 'Sekretariat Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigrasi'],
                ['DIT-PPK', 'Direktorat Perencanaan Perwujudan Kawasan Transmigrasi'],
                ['DIT-PBKT', 'Direktorat Pembangunan Kawasan Transmigrasi'],
                ['DIT-FPPK', 'Direktorat Fasilitasi Penataan Persebaran Penduduk Di Kawasan Transmigrasi'],
                ['DIT-PSPTS', 'Direktorat Pengembangan Satuan Permukiman dan Pusat Satuan Kawasan Pengembangan'],
                ['DIT-PKT', 'Direktorat Pengembangan Kawasan Transmigrasi'],
            ],
        ];

        foreach ($es2Map as $parentCode => $units) {
            foreach ($units as [$code, $name]) {
                Unit::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'level' => 'ES_II', 'parent_id' => $es1[$parentCode]->id],
                );
            }
        }

        // Bagian (Eselon III) di bawah Biro OSDMRB — struktur internal biro
        $biro = $es1['SETJEN']->children()->where('code', 'OSDMRB')->first()
            ?? Unit::where('code', 'OSDMRB')->first();

        foreach ([
            ['BAG-ORG', 'Bagian Organisasi dan Tata Laksana'],
            ['BAG-SDM', 'Bagian Sumber Daya Manusia dan Diklat'],
            ['BAG-RB', 'Bagian Reformasi Birokrasi dan Pengelolaan Kinerja'],
        ] as [$code, $name]) {
            Unit::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'level' => 'ES_III', 'parent_id' => $biro->id],
            );
        }

        // Balai / UPT pelatihan di bawah Sekretariat Jenderal
        foreach ([
            'Balai Besar Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Yogyakarta',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Pekanbaru',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Banjarmasin',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Denpasar',
        ] as $i => $name) {
            Unit::updateOrCreate(
                ['code' => 'BALAI-0'.($i + 1)],
                ['name' => $name, 'level' => 'BALAI', 'parent_id' => $es1['SETJEN']->id],
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
            ['PENYELIA', 'Fungsional Penyelia', 8],
            ['TERAMPIL', 'Fungsional Terampil', 9],
            // pelaksana: non-eselon & non-fungsional
            ['PELAKSANA', 'Pelaksana (Non-Eselon & Non-Fungsional)', 10],
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
            ['STR-SETDITJEN', 'Sekretaris Direktur Jenderal', 'STRUKTURAL', 'ESELON_II'],
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
