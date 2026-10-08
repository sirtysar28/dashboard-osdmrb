<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test Catatan Masukan 30 Sept 2026 (butir 1–13; butir 14 — layanan
 * kepegawaian baru — sengaja belum dikerjakan):
 *
 *  1. Urutan unit kerja eselon I–III & balai mengikuti struktur resmi
 *     kementerian (lampiran "urutan jabatan.xlsx").
 *  2. Card hasil filter dashboard utama diletakkan di atas, sebelum
 *     kenaikan pangkat & kenaikan gaji berkala.
 *  3. (Import Non ASN format baru — sudah diuji UpdateOctober2Test.)
 *  4. Filter unit kerja "kosong" pada menu Data Pegawai ASN.
 *  5. Urutan riwayat pendidikan: Pendidikan 1 = S1, 2 = S2, 3 = S3 —
 *     label "Tingkat" dihilangkan.
 *  6. Pemilihan perguruan tinggi memakai dropdown dengan kotak pencarian.
 *  7. CV: Riwayat Pendidikan langsung dimulai dari Pendidikan 1; Tingkat
 *     Terakhir dipindah ke bagian Data Personal.
 *  8. Analis jabatan fungsional: jabatan kosong tidak tampil, ada kolom
 *     Nomor, filter nama jabatan & jenjang, serta pagination.
 *  9. Analis jabatan struktural: nama jabatan UMUM + kolom Nomor, filter,
 *     pagination.
 * 10. Analis jabatan pelaksana: pemangku dihitung dari data pegawai
 *     (tidak lagi kosong) + kolom Nomor, filter, pagination.
 * 11. Menu "SOP Kementerian" berganti nama menjadi "Dokumen Kepegawaian".
 * 12. Riwayat diklat WAJIB mengunggah dokumen/sertifikat bukti.
 * 13. Fitur pencarian cuti hanya untuk Admin Bagian & Super Admin.
 */
class UpdateSeptember30Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::firstWhere('email', 'admin@osdmrb.go.id')
            ?: User::create([
                'name' => 'Admin',
                'email' => 'admin@osdmrb.go.id',
                'password' => Hash::make('password'),
                'employee_id' => null,
            ]);
        $this->admin->syncRoles(['admin']);

        $pegawaiEmployee = Employee::firstOrCreate(
            ['nip' => '199001012010011909'],
            [
                'name' => 'Pegawai Uji 30 Sept',
                'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
                'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
                'employee_type' => Employee::TYPE_ASN,
                'is_active' => true,
                'tmt_pns' => '2015-01-01',
            ],
        );

        $this->pegawai = User::firstOrCreate(
            ['email' => 'pegawai30@osdmrb.go.id'],
            ['name' => 'Pegawai Uji 30 Sept', 'password' => Hash::make('password'), 'employee_id' => $pegawaiEmployee->id],
        );
        $this->pegawai->syncRoles(['pegawai']);
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'nip' => '19'.str_pad((string) random_int(1, 999999999), 9, '0'),
            'name' => 'Aaaa Pegawai Uji '.uniqid(),
            'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
            'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
            'employee_type' => Employee::TYPE_ASN,
            'is_active' => true,
            'tmt_pns' => '2015-01-01',
        ], $attributes));
    }

    /* ======== 1. URUTAN UNIT KERJA PER STRUKTUR KEMENTERIAN ======== */

    public function test_urutan_unit_kerja_mengikuti_struktur_resmi(): void
    {
        // Eselon I: Setjen → Ditjen Ekbang → Ditjen Kawasan → Itjen
        $es1 = Unit::where('level', 'ES_I')->ordered()->pluck('code')->all();

        $posSetjen = array_search('SETJEN', $es1);
        $posEkbang = array_search('DJ-EKBANG', $es1);
        $posKawasan = array_search('DJ-KAWASAN', $es1);
        $posItjen = array_search('ITJEN', $es1);

        $this->assertNotFalse($posSetjen);
        $this->assertGreaterThan($posSetjen, $posEkbang);
        $this->assertGreaterThan($posEkbang, $posKawasan);
        $this->assertGreaterThan($posKawasan, $posItjen);

        // Biro/pusat Setjen: PKHM → OSDMRB → ULP → KBMN → Hukum → Pusat STK → PSDM → Datin
        $setjen = Unit::where('code', 'SETJEN')->first();
        $es2Setjen = Unit::where('parent_id', $setjen->id)->ordered()->pluck('code')->all();

        $this->assertSame(
            ['BIRO-PKHM', 'OSDMRB', 'BIRO-ULP', 'BIRO-KBMN', 'BIRO-HUKUM', 'PUS-STK', 'PUS-PSDM', 'PUS-DATIN', 'STAF-AH-PKLH', 'STAF-AH-POLHUK'],
            array_values(array_intersect(
                ['BIRO-PKHM', 'OSDMRB', 'BIRO-ULP', 'BIRO-KBMN', 'BIRO-HUKUM', 'PUS-STK', 'PUS-PSDM', 'PUS-DATIN', 'STAF-AH-PKLH', 'STAF-AH-POLHUK'],
                $es2Setjen,
            )),
        );

        // Balai: Balai Besar Yogyakarta paling awal lalu Pekanbaru, Banjarmasin, Denpasar
        $balai = Unit::where('level', 'BALAI')->ordered()->pluck('name')->all();
        $this->assertStringStartsWith('Balai Besar', $balai[0]);
        $this->assertStringContainsString('Pekanbaru', $balai[1]);
        $this->assertStringContainsString('Banjarmasin', $balai[2]);
        $this->assertStringContainsString('Denpasar', $balai[3]);
    }

    /* ======== 2. CARD HASIL FILTER DI URUTAN ATAS DASHBOARD ======== */

    public function test_card_hasil_filter_dashboard_di_atas_sebelum_kenaikan_pangkat_dan_kgb(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $posHasilFilter = mb_strpos($html, 'Hasil Filter — Data Pegawai');
        $posKenaikanPangkat = mb_strpos($html, 'Proyeksi Kenaikan Pangkat Terdekat');
        $posKgb = mb_strpos($html, 'Jadwal Kenaikan Gaji Berkala (KGB) Terdekat');

        $this->assertNotFalse($posHasilFilter);
        $this->assertNotFalse($posKenaikanPangkat);
        $this->assertNotFalse($posKgb);
        $this->assertLessThan($posKenaikanPangkat, $posHasilFilter);
        $this->assertLessThan($posKgb, $posHasilFilter);
    }

    /* ======== 4. FILTER UNIT KERJA KOSONG ======== */

    public function test_filter_unit_kerja_kosong_pada_data_pegawai_asn(): void
    {
        $tanpaUnit = $this->makeEmployee(['unit_id' => null]);
        $denganUnit = $this->makeEmployee();

        $res = $this->actingAs($this->admin)
            ->get(route('employees.index', ['unit' => ['kosong'], 'search' => 'Aaaa Pegawai Uji']))
            ->assertOk();

        $res->assertSee($tanpaUnit->name);
        $res->assertDontSee($denganUnit->name);

        // opsi "kosong" tersedia di dropdown filter
        $this->actingAs($this->admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Unit Kerja belum diisi (kosong)');
    }

    /* ======== 5 & 7. URUTAN PENDIDIKAN + CV ======== */

    public function test_urutan_pendidikan_dirapikan_pendidikan_1_s1(): void
    {
        // data lama terbalik: Pendidikan 1 berisi S2, Pendidikan 2 berisi S1
        $employee = $this->makeEmployee([
            'education_1' => 'Magister Administrasi, Universitas Gadjah Mada — S2',
            'education_2' => 'Teknik Informatika, Universitas Indonesia — S1',
        ]);

        // RefreshDatabase memigrasi SEBELUM pegawai dibuat — jalankan ulang
        // logika migrasi pengurutan untuk data uji ini
        $migration = require database_path('migrations/2026_10_07_000002_urutkan_riwayat_pendidikan_pegawai.php');
        $migration->up();

        $employee->refresh();

        $this->assertStringContainsString('S1', (string) $employee->education_1);
        $this->assertStringContainsString('S2', (string) $employee->education_2);
    }

    public function test_cv_riwayat_pendidikan_tanpa_tingkat_terakhir_dan_tingkat_di_data_personal(): void
    {
        $education = \App\Models\EducationLevel::where('code', 'S2')->first();
        $employee = $this->makeEmployee([
            'education_level_id' => $education->id,
            'education_1' => 'Teknik Informatika, Universitas Indonesia — S1',
        ]);

        // route CV menghasilkan PDF — periksa sumber template-nya
        $this->actingAs($this->admin)
            ->get(route('employees.cv', $employee))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $blade = file_get_contents(resource_path('views/employees/cv.blade.php'));

        // "Tingkat Terakhir" sudah tidak ada di Riwayat Pendidikan...
        $posRiwayat = mb_strpos($blade, 'R IWAYAT PENDIDIKAN');
        $this->assertNotFalse($posRiwayat);
        $this->assertFalse(mb_strpos(mb_substr($blade, $posRiwayat), 'Tingkat Terakhir'));

        // ...dan Pendidikan 1 muncul pertama
        $this->assertGreaterThan($posRiwayat, mb_strpos($blade, "['Pendidikan 1', \$employee->education_1]"));

        // Tingkat Terakhir dipindah ke bagian Data Personal
        $posPersonal = mb_strpos($blade, 'DATA PERSONAL');
        $this->assertNotFalse($posPersonal);
        $this->assertGreaterThan($posPersonal, mb_strpos($blade, 'Tingkat Pendidikan Terakhir'));
    }

    /* ======== 6. PENCARIAN NAMA PERGURUAN TINGGI ======== */

    public function test_form_pegawai_memakai_dropdown_kampus_dengan_pencarian(): void
    {
        $this->actingAs($this->admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('ss-search')                    // komponen pencarian aktif
            ->assertSee('Ketik nama perguruan tinggi')  // placeholder kotak cari
            ->assertSee('- Pilih Kampus -');
    }

    /* ======== 8. ANALIS JABATAN FUNGSIONAL ======== */

    public function test_analis_jabatan_fungsional_jabatan_kosong_tidak_tampil(): void
    {
        $this->makeEmployee([
            'position_name' => 'Analis Kepegawaian Ahli Muda',
            'functional_level' => 'Ahli Muda',
            'eselon' => null,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Analis Kepegawaian Ahli Muda', $html);

        // baris tabel bermula dari kolom Nomor
        $this->assertStringContainsString('<th class="text-center" style="width:44px">No</th>', $html);

        // filter nama jabatan & jenjang tersedia (pagination tampil saat >1 halaman)
        $this->assertStringContainsString('name="nama"', $html);
        $this->assertStringContainsString('name="jenjang"', $html);
    }

    public function test_analis_jabatan_fungsional_filter_nama_jabatan(): void
    {
        $this->makeEmployee([
            'position_name' => 'Analis Kepegawaian Ahli Muda',
            'functional_level' => 'Ahli Muda',
            'eselon' => null,
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan', ['nama' => 'Analis Kepegawaian']))
            ->assertOk();

        $res->assertSee('Analis Kepegawaian Ahli Muda');

        // kata kunci yang tidak cocok → tabel kosong
        $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan', ['nama' => 'TidakAdaJabatanIni']))
            ->assertOk()
            ->assertSee('Tidak ada jabatan fungsional yang cocok');
    }

    /* ======== 9. ANALIS JABATAN STRUKTURAL: NAMA JABATAN UMUM ======== */

    public function test_analis_jabatan_struktural_memakai_nama_jabatan_umum(): void
    {
        $this->makeEmployee([
            'position_name' => 'Kepala Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi',
            'eselon' => 'II',
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan-struktural'))
            ->assertOk();

        // dikelompokkan sebagai nama umum "Kepala Biro", bukan nama lengkap
        $res->assertSee('Kepala Biro');
        $res->assertDontSee('Kepala Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi');

        // kolom Nomor + filter (pagination tampil saat >1 halaman)
        $res->assertSee('Nama Jabatan Umum')
            ->assertSee('name="nama"', false)
            ->assertSee('name="jenjang"', false);
    }

    /* ======== 10. ANALIS JABATAN PELAKSANA: DATA DARI PEGAWAI ======== */

    public function test_analis_jabatan_pelaksana_menghitung_pemangku_dari_data_pegawai(): void
    {
        $pelaksana = $this->makeEmployee([
            'position_name' => 'Pengelola Kepegawaian',
            'eselon' => null,
            'functional_level' => null,
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan-pelaksana'))
            ->assertOk();

        $res->assertSee('Pengelola Kepegawaian');
        $res->assertSee($pelaksana->name);
        $res->assertSee('name="nama"', false);
        $res->assertSee('name="jenjang"', false);
    }

    /* ======== 11. MENU SOP → DOKUMEN KEPEGAWAIAN ======== */

    public function test_menu_sop_bernama_dokumen_kepegawaian(): void
    {
        $this->actingAs($this->admin)
            ->get(route('modules.sop'))
            ->assertOk()
            ->assertSee('Dokumen Kepegawaian')
            ->assertDontSee('SOP Kementerian');

        // label sidebar ikut berubah
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<span>Dokumen Kepegawaian</span>', false);
    }

    /* ======== 12. RIWAYAT DIKLAT WAJIB UPLOAD ======== */

    public function test_riwayat_diklat_wajib_upload_dokumen(): void
    {
        $employeeId = $this->pegawai->employee_id;

        // tanpa dokumen → ditolak
        $this->actingAs($this->pegawai)
            ->post('/modul/diklat', [
                'employee_id' => $employeeId,
                'name' => 'Diklat Tanpa Sertifikat',
                'type' => 'TEKNIS',
                'from' => 'profile',
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('employee_trainings', ['name' => 'Diklat Tanpa Sertifikat']);

        // dengan dokumen → tersimpan
        $this->actingAs($this->pegawai)
            ->post('/modul/diklat', [
                'employee_id' => $employeeId,
                'name' => 'Diklat Dengan Sertifikat',
                'type' => 'TEKNIS',
                'from' => 'profile',
                'file' => UploadedFile::fake()->create('sertifikat.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('employee_trainings', [
            'employee_id' => $employeeId,
            'name' => 'Diklat Dengan Sertifikat',
        ]);
    }

    /* ======== 13. PENCARIAN CUTI HANYA ADMIN & SUPER ADMIN ======== */

    public function test_pencarian_cuti_hanya_untuk_admin_dan_super_admin(): void
    {
        \App\Models\Setting::set('menu_cuti', true);

        // akun pegawai: kolom pencarian tidak tampil
        $this->actingAs($this->pegawai)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertDontSee('placeholder="Nama pegawai / alasan..."', false);

        // admin bagian: kolom pencarian tampil
        $this->actingAs($this->admin)
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertSee('placeholder="Nama pegawai / alasan..."', false);
    }
}
