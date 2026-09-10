<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test fitur update 8 September 2026 — bagian 2 (catatan sore):
 *
 * 1. Menu & modul Analisis Jabatan Struktural
 * 2. Zoom (minimize/maximize) bagan struktur organisasi + bagan resmi SOTK
 * 3. Pengumuman tampil sebagai kartu (2 kolom, maks 4/halaman) + marquee judul
 * 4. Daftar nama unit kerja diperbarui sesuai file resmi (SOTK)
 * 5. Info ukuran gambar kartu portrait 600 x 750 px (4:5) maks 1 MB + upload/URL
 * 6. Filter kenaikan jabatan: semua / tahun ini / <= 1 tahun / <= 4 tahun
 */
class UpdateSeptember8Part2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
                'is_active' => true,
            ]);

        $this->admin->syncRoles(['admin', 'super_admin']);
    }

    /* ================= MODUL ANALISIS JABATAN STRUKTURAL ================= */

    public function test_modul_analisis_jabatan_struktural_termuat(): void
    {
        $this->actingAs($this->admin)
            ->get('/modul/analisis-jabatan-struktural')
            ->assertOk()
            ->assertSee('Analisis Jabatan Struktural')
            ->assertSee('Daftar Jabatan Struktural')
            ->assertSee('Distribusi Perjenjang Eselon');

        // menu sidebar ikut tampil
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Analisis Jabatan Struktural')
            ->assertSee(route('modules.analisis-jabatan-struktural'), false);
    }

    /* ================= UNIT KERJA PER FILE RESMI ================= */

    public function test_daftar_unit_kerja_sesuai_sotk(): void
    {
        $expected = [
            'Sekretariat Jenderal' => 'ES_I',
            'Inspektorat Jenderal' => 'ES_I',
            'Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi' => 'ES_I',
            'Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigrasi' => 'ES_I',
            'Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi' => 'ES_II',
            'Biro Perencanaan, Kerja Sama, dan Hubungan Masyarakat' => 'ES_II',
            'Biro Umum dan Layanan Pengadaan' => 'ES_II',
            'Pusat Strategi Kebijakan Transmigrasi' => 'ES_II',
            'Inspektorat I' => 'ES_II',
            'Direktorat Promosi dan Pemasaran Produk Unggulan Transmigrasi' => 'ES_II',
            'Balai Besar Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Yogyakarta' => 'BALAI',
            'Balai Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Denpasar' => 'BALAI',
        ];

        foreach ($expected as $name => $level) {
            $this->assertDatabaseHas('units', ['name' => $name, 'level' => $level]);
        }

        // unit contoh lama tidak boleh ada lagi
        $this->assertDatabaseMissing('units', ['name' => 'Direktorat Jenderal Pembinaan']);
        $this->assertDatabaseMissing('units', ['name' => 'Balai Wilayah Jakarta']);
    }

    public function test_struktur_organisasi_bisa_diperkecil_dan_diperbesar(): void
    {
        $this->actingAs($this->admin)
            ->get('/modul/struktur-organisasi')
            ->assertOk()
            // tombol perkecil (minimize) / perbesar (maximize)
            ->assertSee('data-zoom-out', false)
            ->assertSee('data-zoom-in', false)
            ->assertSee('data-zoom-reset', false)
            // bagan resmi Kementerian dari transmigrasi.go.id
            ->assertSee('Bagan Resmi Kementerian (SOTK)')
            ->assertSee('https://www.transmigrasi.go.id/profil/struktur-organisasi/', false)
            ->assertSee('images/struktur-organisasi.png', false);
    }

    /* ================= KARTU PENGUMUMAN (menggantikan slider) ================= */

    public function test_pengumuman_tampil_sebagai_kartu_dengan_marquee_judul(): void
    {
        \App\Models\Announcement::query()->delete();

        \App\Models\Announcement::create([
            'title' => 'Pengumuman A',
            'is_active' => true,
        ]);
        \App\Models\Announcement::create([
            'title' => 'Pengumuman B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk()
            // kartu pengumuman + popup detail per kartu
            ->assertSee('announcement-card', false)
            ->assertSee('announcementDetail', false)
            ->assertSee('Pengumuman A')
            ->assertSee('Pengumuman B')
            // teks berjalan tunggal berisi judul-judul kartu (dipisah bullet)
            ->assertSee('announcement-marquee-text', false)
            ->assertSee('&nbsp;&bull;&nbsp;', false)
            // kecepatan marquee dihitung dari panjang teks
            ->assertSee('scrollWidth', false)
            ->assertSee('animationDuration', false);
    }

    public function test_lebih_dari_empat_kartu_pengumuman_jadi_halaman_geser(): void
    {
        \App\Models\Announcement::query()->delete();

        foreach (['Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam'] as $i => $nama) {
            \App\Models\Announcement::create([
                'title' => 'Pengumuman '.$nama,
                'is_active' => true,
                'created_at' => now()->addSeconds($i),
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk()
            // 6 kartu -> 2 halaman (maks 4 kartu per halaman)
            ->assertSee('announcement-pages', false)
            ->assertSee('announcement-counter', false)
            ->assertSee('announcement-arrow-next', false)
            ->assertSee('announcement-dot', false)
            // geser halaman lewat swipe layar sentuh
            ->assertSee('touchstart', false);
    }

    /* ================= INFO UKURAN GAMBAR KARTU ================= */

    public function test_menu_upload_banner_menampilkan_info_ukuran(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.announcements'))
            ->assertOk()
            ->assertSee('600')
            ->assertSee('750')
            ->assertSee('maksimal 1 MB')
            // bisa lewat upload berkas maupun URL gambar
            ->assertSee('name="image_url"', false)
            ->assertSee('Link Button', false);
    }

    /* ================= FILTER KENAIKAN JABATAN ================= */

    public function test_filter_kenaikan_jabatan_memiliki_empat_opsi(): void
    {
        $this->actingAs($this->admin)
            ->get('/employees')
            ->assertOk()
            ->assertSee('value="tahun_ini"', false)
            ->assertSee('Kurang dari = 1 tahun')
            ->assertSee('Kurang dari = 4 tahun');
    }

    public function test_filter_kenaikan_jabatan_tahun_ini_dan_batas_tahun(): void
    {
        $this->actingAs($this->admin);

        $employee = Employee::create([
            'nip' => '19800101200012100'.random_int(100, 999),
            'name' => 'Pegawai Uji Kenaikan Jabatan',
            'gender' => 'L',
            'employee_type' => 'asn',
            'is_active' => true,
        ]);

        // kenaikan akhir tahun ini -> tahun ini & <= 1 tahun & <= 4 tahun
        $employee->update([
            'next_promotion_date' => now()->endOfYear()->subDays(2)->toDateString(),
        ]);

        // pencarian mempersempit hasil; hitungan header "Daftar Pegawai (N)" menjadi acuan
        $this->get('/employees?naik=tahun_ini&search='.urlencode($employee->name))->assertOk()->assertSee($employee->name);
        $this->get('/employees?naik=1&search='.urlencode($employee->name))->assertOk()->assertSee($employee->name);
        $this->get('/employees?naik=4&search='.urlencode($employee->name))->assertOk()->assertSee($employee->name);

        // kenaikan 3 tahun lagi -> hanya masuk <= 4 tahun
        $employee->update([
            'next_promotion_date' => now()->addYears(3)->toDateString(),
        ]);

        $this->get('/employees?naik=tahun_ini&search='.urlencode($employee->name))->assertOk()->assertSee('Daftar Pegawai (0)');
        $this->get('/employees?naik=1&search='.urlencode($employee->name))->assertOk()->assertSee('Daftar Pegawai (0)');
        $this->get('/employees?naik=4&search='.urlencode($employee->name))->assertOk()->assertSee('Daftar Pegawai (1)');
    }

    public function test_filter_kenaikan_jabatan_memakai_estimasi_tmt_golongan(): void
    {
        $this->actingAs($this->admin);

        $employee = Employee::create([
            'nip' => '19850101201012100'.random_int(100, 999),
            'name' => 'Pegawai Uji Estimasi TMT',
            'gender' => 'P',
            'employee_type' => 'asn',
            'is_active' => true,
        ]);

        // tanpa kolom kenaikan, estimasi = TMT golongan + 4 tahun
        $employee->update([
            'next_promotion_date' => null,
            'tmt_golongan' => now()->subYears(3)->subMonths(6)->toDateString(), // naik ±6 bulan lagi
        ]);

        $this->get('/employees?naik=1&search='.urlencode($employee->name))->assertOk()->assertSee($employee->name);

        $employee->update([
            'tmt_golongan' => now()->subYear()->toDateString(), // baru 1 tahun berjalan -> naik 3 tahun lagi
        ]);

        $this->get('/employees?naik=1&search='.urlencode($employee->name))->assertOk()->assertSee('Daftar Pegawai (0)');
        $this->get('/employees?naik=4&search='.urlencode($employee->name))->assertOk()->assertSee('Daftar Pegawai (1)');
    }
}
