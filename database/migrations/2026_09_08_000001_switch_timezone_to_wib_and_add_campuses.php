<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur update 8 September 2026 — bagian 1:
 *
 * 1. Zona waktu aplikasi diubah dari UTC (GMT) menjadi Asia/Jakarta (GMT+7 /
 *    WIB). Semua penampilan waktu (audit log, email notifikasi, OTP, dsb.)
 *    otomatis mengikuti WIB.
 *
 * 2. Data lama yang sempat tersimpan dengan jam UTC digeser +7 jam agar
 *    tampilan riwayat (audit log, waktu OTP, dsb.) tetap konsisten dengan
 *    WIB. Pergeseran hanya mengenai baris yang ada SEBELUM migrasi ini
 *    dijalankan — karena itu jalankan `php artisan migrate` bersamaan
 *    dengan deployment kode ini (jangan ditunda setelah aplikasi dipakai).
 *
 * 3. Master data KAMPUS (perguruan tinggi) untuk dropdown pendidikan
 *    terakhir S1/S2/S3 pada form pegawai.
 */
return new class extends Migration
{
    /**
     * Kolom timestamp (UTC lama) yang perlu digeser +7 jam menjadi WIB.
     *
     * @var array<string, list<string>>
     */
    private array $shiftColumns = [
        'audit_logs' => ['created_at', 'updated_at'],
        'users' => ['email_verified_at', 'otp_expires_at', 'otp_sent_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'letters' => ['created_at', 'updated_at'],
        'letter_types' => ['created_at', 'updated_at'],
        'letter_logs' => ['created_at', 'updated_at'],
        'surveys' => ['created_at', 'updated_at'],
        'announcements' => ['created_at', 'updated_at'],
        'archives' => ['created_at', 'updated_at'],
        'archive_categories' => ['created_at', 'updated_at'],
        'archive_loans' => ['created_at', 'updated_at'],
        'sops' => ['created_at', 'updated_at'],
        'employee_trainings' => ['created_at', 'updated_at'],
        'employees' => ['created_at', 'updated_at'],
        'settings' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shiftLegacyTimestampsToWib();
        $this->createCampusesTable();
    }

    /**
     * Geser seluruh timestamp lama (jam UTC) +7 jam menjadi jam WIB.
     * Hanya dijalankan pada MySQL (basis data produksi) — basis data test
     * (SQLite) selalu kosong sehingga tidak perlu digeser.
     */
    private function shiftLegacyTimestampsToWib(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->shiftColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)
                    ->whereNotNull($column)
                    ->update([$column => DB::raw("DATE_ADD(`{$column}`, INTERVAL 7 HOUR)")]);
            }
        }
    }

    /**
     * Tabel master kampus/perguruan tinggi + data awal.
     */
    private function createCampusesTable(): void
    {
        if (Schema::hasTable('campuses')) {
            return;
        }

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                 // nama perguruan tinggi
            $table->string('city', 100)->nullable();          // kota
            $table->string('type', 20)->default('negeri');    // negeri | swasta | luar_negeri
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now()->toDateTimeString();
        $rows = [];
        $sort = 0;

        foreach ($this->campusSeedData() as [$name, $city, $type]) {
            $rows[] = [
                'name' => $name,
                'city' => $city,
                'type' => $type,
                'sort_order' => $sort += 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // chunk agar binding tidak terlalu panjang di MySQL
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('campuses')->insertOrIgnore($chunk);
        }
    }

    /**
     * Daftar kampus awal (PTN populer, PTS besar, politeknik & luar negeri).
     *
     * @return list<array{0: string, 1: string|null, 2: string}>
     */
    private function campusSeedData(): array
    {
        return [
            // ---- Perguruan Tinggi Negeri ----
            ['Universitas Indonesia', 'Depok', 'negeri'],
            ['Universitas Gadjah Mada', 'Yogyakarta', 'negeri'],
            ['Institut Teknologi Bandung', 'Bandung', 'negeri'],
            ['Institut Pertanian Bogor', 'Bogor', 'negeri'],
            ['Institut Teknologi Sepuluh Nopember', 'Surabaya', 'negeri'],
            ['Universitas Diponegoro', 'Semarang', 'negeri'],
            ['Universitas Airlangga', 'Surabaya', 'negeri'],
            ['Universitas Brawijaya', 'Malang', 'negeri'],
            ['Universitas Padjadjaran', 'Bandung', 'negeri'],
            ['Universitas Hasanuddin', 'Makassar', 'negeri'],
            ['Universitas Sumatera Utara', 'Medan', 'negeri'],
            ['Universitas Sebelas Maret', 'Surakarta', 'negeri'],
            ['Universitas Sriwijaya', 'Palembang', 'negeri'],
            ['Universitas Andalas', 'Padang', 'negeri'],
            ['Universitas Udayana', 'Denpasar', 'negeri'],
            ['Universitas Lampung', 'Bandar Lampung', 'negeri'],
            ['Universitas Negeri Jakarta', 'Jakarta', 'negeri'],
            ['Universitas Negeri Yogyakarta', 'Yogyakarta', 'negeri'],
            ['Universitas Negeri Semarang', 'Semarang', 'negeri'],
            ['Universitas Negeri Padang', 'Padang', 'negeri'],
            ['Universitas Negeri Makassar', 'Makassar', 'negeri'],
            ['Universitas Negeri Malang', 'Malang', 'negeri'],
            ['Universitas Negeri Medan', 'Medan', 'negeri'],
            ['Universitas Negeri Surabaya', 'Surabaya', 'negeri'],
            ['Universitas Pendidikan Indonesia', 'Bandung', 'negeri'],
            ['Universitas Terbuka', 'Tangerang Selatan', 'negeri'],
            ['Universitas Islam Negeri Syarif Hidayatullah Jakarta', 'Tangerang Selatan', 'negeri'],
            ['Universitas Islam Negeri Sunan Kalijaga', 'Yogyakarta', 'negeri'],
            ['Universitas Islam Negeri Walisongo', 'Semarang', 'negeri'],
            ['Universitas Islam Negeri Sultan Syarif Kasim', 'Pekanbaru', 'negeri'],
            ['Universitas Islam Negeri Alauddin', 'Makassar', 'negeri'],
            ['Universitas Muhammadiyah Prof. Dr. Hamka', 'Jakarta', 'negeri'],
            ['Universitas Pendidikan Ganesha', 'Singaraja', 'negeri'],
            ['Universitas Jenderal Soedirman', 'Purwokerto', 'negeri'],
            ['Universitas Jember', 'Jember', 'negeri'],
            ['Universitas Mataram', 'Mataram', 'negeri'],
            ['Universitas Sam Ratulangi', 'Manado', 'negeri'],
            ['Universitas Tanjungpura', 'Pontianak', 'negeri'],
            ['Universitas Mulawarman', 'Samarinda', 'negeri'],
            ['Universitas Cenderawasih', 'Jayapura', 'negeri'],
            ['Universitas Riau', 'Pekanbaru', 'negeri'],
            ['Universitas Bengkulu', 'Bengkulu', 'negeri'],
            ['Universitas Jambi', 'Jambi', 'negeri'],
            ['Universitas Syiah Kuala', 'Banda Aceh', 'negeri'],
            ['Universitas Malikussaleh', 'Lhokseumawe', 'negeri'],
            ['Universitas Palangka Raya', 'Palangka Raya', 'negeri'],
            ['Universitas Lambung Mangkurat', 'Banjarmasin', 'negeri'],
            ['Universitas Halu Oleo', 'Kendari', 'negeri'],
            ['Universitas Nusa Cendana', 'Kupang', 'negeri'],
            ['Universitas Tadulako', 'Palu', 'negeri'],
            ['Universitas Khairun', 'Ternate', 'negeri'],
            ['Institut Teknologi Sumatera', 'Bandar Lampung', 'negeri'],
            ['Institut Teknologi Kalimantan', 'Balikpapan', 'negeri'],
            ['Politeknik Negeri Jakarta', 'Depok', 'negeri'],
            ['Politeknik Negeri Bandung', 'Bandung', 'negeri'],
            ['Politeknik Negeri Semarang', 'Semarang', 'negeri'],
            ['Politeknik Negeri Medan', 'Medan', 'negeri'],
            ['Politeknik Negeri Sriwijaya', 'Palembang', 'negeri'],
            ['Politeknik Negeri Makassar', 'Makassar', 'negeri'],
            ['Politeknik Kesehatan Kemenkes Jakarta I', 'Jakarta', 'negeri'],
            ['Sekolah Tinggi Akuntansi dan Keuangan Negara', 'Bekasi', 'negeri'],
            ['Institut Pemerintahan Dalam Negeri', 'Sumedang', 'negeri'],
            ['Sekolah Tinggi Manajemen IMMI', 'Jakarta', 'negeri'],

            // ---- Perguruan Tinggi Swasta ----
            ['Universitas Trisakti', 'Jakarta', 'swasta'],
            ['Universitas Atma Jaya', 'Jakarta', 'swasta'],
            ['Universitas Katolik Parahyangan', 'Bandung', 'swasta'],
            ['Universitas Tarumanagara', 'Jakarta', 'swasta'],
            ['Universitas Mercu Buana', 'Jakarta', 'swasta'],
            ['Universitas Gunadarma', 'Depok', 'swasta'],
            ['Universitas Bina Nusantara', 'Jakarta', 'swasta'],
            ['Universitas Pelita Harapan', 'Tangerang', 'swasta'],
            ['Universitas Esa Unggul', 'Jakarta', 'swasta'],
            ['Universitas Jayabaya', 'Jakarta', 'swasta'],
            ['Universitas Krisnadwipayana', 'Jakarta', 'swasta'],
            ['Universitas Indraprasta PGRI', 'Jakarta', 'swasta'],
            ['Universitas Nusa Mandiri', 'Jakarta', 'swasta'],
            ['Universitas Pamulang', 'Tangerang Selatan', 'swasta'],
            ['Universitas Bakrie', 'Jakarta', 'swasta'],
            ['Universitas Kristen Indonesia', 'Jakarta', 'swasta'],
            ['Universitas 17 Agustus 1945 Jakarta', 'Jakarta', 'swasta'],
            ['Universitas Borobudur', 'Jakarta', 'swasta'],
            ['Universitas Muhammadiyah Jakarta', 'Jakarta', 'swasta'],
            ['Universitas Muhammadiyah Yogyakarta', 'Yogyakarta', 'swasta'],
            ['Universitas Muhammadiyah Surakarta', 'Surakarta', 'swasta'],
            ['Universitas Muhammadiyah Magelang', 'Magelang', 'swasta'],
            ['Universitas Ahmad Dahlan', 'Yogyakarta', 'swasta'],
            ['Universitas Islam Indonesia', 'Yogyakarta', 'swasta'],
            ['Universitas Sanata Dharma', 'Yogyakarta', 'swasta'],
            ['Universitas Katolik Soegijapranata', 'Semarang', 'swasta'],
            ['Universitas Dian Nuswantoro', 'Semarang', 'swasta'],
            ['Universitas Stikubank', 'Semarang', 'swasta'],
            ['Universitas Islam Sultan Agung', 'Semarang', 'swasta'],
            ['Universitas PGRI Semarang', 'Semarang', 'swasta'],
            ['Universitas Katholik Widya Mandala', 'Surabaya', 'swasta'],
            ['Universitas Surabaya', 'Surabaya', 'swasta'],
            ['Universitas Petra', 'Surabaya', 'swasta'],
            ['Universitas Ciputra', 'Surabaya', 'swasta'],
            ['Universitas Ma Chung', 'Malang', 'swasta'],
            ['Universitas Widyagama', 'Malang', 'swasta'],
            ['Universitas Islam Malang', 'Malang', 'swasta'],
            ['Universitas Malahayati', 'Bandar Lampung', 'swasta'],
            ['Universitas Bandar Lampung', 'Bandar Lampung', 'swasta'],
            ['Universitas Telkom', 'Bandung', 'swasta'],
            ['Universitas Kristen Maranatha', 'Bandung', 'swasta'],
            ['Universitas Widyatama', 'Bandung', 'swasta'],
            ['Universitas Islam Bandung', 'Bandung', 'swasta'],
            ['Universitas Buddhi Dharma', 'Tangerang', 'swasta'],
            ['Universitas Multimedia Nusantara', 'Tangerang', 'swasta'],
            ['Institut Teknologi dan Bisnis Kalbe', 'Jakarta', 'swasta'],
            ['Sekolah Tinggi Ilmu Ekonomi Indonesia Jakarta', 'Jakarta', 'swasta'],
            ['Sekolah Tinggi Ilmu Ekonomi Jakarta', 'Jakarta', 'swasta'],
            ['Sekolah Tinggi Ilmu Ekonomi IBII', 'Jakarta', 'swasta'],
            ['Institut Bisnis dan Informatika Kosgora 1957', 'Jakarta', 'swasta'],

            // ---- Luar Negeri ----
            ['National University of Singapore', 'Singapura', 'luar_negeri'],
            ['Nanyang Technological University', 'Singapura', 'luar_negeri'],
            ['Universiti Malaya', 'Kuala Lumpur', 'luar_negeri'],
            ['Universiti Kebangsaan Malaysia', 'Bangi', 'luar_negeri'],
            ['Universiti Putra Malaysia', 'Serdang', 'luar_negeri'],
            ['Universiti Teknologi Malaysia', 'Johor Bahru', 'luar_negeri'],
            ['University of Melbourne', 'Melbourne', 'luar_negeri'],
            ['Australian National University', 'Canberra', 'luar_negeri'],
            ['University of Queensland', 'Brisbane', 'luar_negeri'],
            ['Monash University', 'Melbourne', 'luar_negeri'],
            ['RMIT University', 'Melbourne', 'luar_negeri'],
            ['University of Sydney', 'Sydney', 'luar_negeri'],
            ['University of Leeds', 'Leeds', 'luar_negeri'],
            ['University of Birmingham', 'Birmingham', 'luar_negeri'],
            ['University of Twente', 'Enschede', 'luar_negeri'],
            ['Delft University of Technology', 'Delft', 'luar_negeri'],
            ['Erasmus University Rotterdam', 'Rotterdam', 'luar_negeri'],
            ['Leiden University', 'Leiden', 'luar_negeri'],
            ['Wageningen University & Research', 'Wageningen', 'luar_negeri'],
            ['University of Tokyo', 'Tokyo', 'luar_negeri'],
            ['Kyoto University', 'Kyoto', 'luar_negeri'],
        ];
    }

    public function down(): void
    {
        Schema::dropIfExists('campuses');

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->shiftColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    DB::table($table)
                        ->whereNotNull($column)
                        ->update([$column => DB::raw("DATE_SUB(`{$column}`, INTERVAL 7 HOUR)")]);
                }
            }
        }
    }
};
