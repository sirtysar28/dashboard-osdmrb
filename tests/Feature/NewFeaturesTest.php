<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test fitur baru: captcha huruf, master data edit, export, import.
 */
class NewFeaturesTest extends TestCase
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
            ]);
    }

    public function test_halaman_login_menampilkan_captcha_huruf(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('captchaCode', false);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z]{5}$/', session('login_captcha'));
    }

    public function test_refresh_captcha_mengembalikan_huruf(): void
    {
        $response = $this->getJson('/refresh-captcha');

        $response->assertOk()
            ->assertJsonStructure(['code']);

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z]{5}$/', $response->json('code'));
    }

    public function test_login_dengan_captcha_huruf_berhasil(): void
    {
        $this->get('/login'); // menghasilkan captcha di session

        $response = $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'password',
            'captcha' => strtolower(session('login_captcha')),
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_halaman_master_data_bisa_diakses_dan_memuat_modals(): void
    {
        $response = $this->actingAs($this->admin)->get('/master');

        $response->assertOk()
            ->assertSee('editUnit', false)
            ->assertSee('Ubah Unit Kerja')
            ->assertSee('/master/export');
    }

    public function test_update_status_kepegawaian_lewat_route_baru(): void
    {
        $status = \App\Models\EmploymentStatus::first();

        $response = $this->actingAs($this->admin)
            ->put("/master/statuses/{$status->id}", [
                'code' => $status->code,
                'name' => 'Nama Baru Test',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employment_statuses', [
            'id' => $status->id,
            'name' => 'Nama Baru Test',
        ]);
    }

    public function test_export_pegawai_excel_dan_pdf(): void
    {
        $this->actingAs($this->admin);

        $excel = $this->get('/employees/export?format=xlsx');
        $excel->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $excel->headers->get('Content-Type')
        );

        $pdf = $this->get('/employees/export?format=pdf');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_export_master_data_dan_surat(): void
    {
        $this->actingAs($this->admin);

        $this->get('/master/export?format=xlsx')->assertOk();
        $this->get('/master/export?format=pdf')->assertOk();
        $this->get('/surat/export?format=pdf')->assertOk();
        $this->get('/surat/export?format=xlsx')->assertOk();
        $this->get('/arsip/export?format=pdf')->assertOk();
        $this->get('/arsip/export?format=xlsx')->assertOk();
        $this->get('/arsip-pinjam/export?format=xlsx')->assertOk();
        $this->get('/pengguna/export?format=pdf')->assertOk();
    }

    public function test_halaman_import_pegawai_dan_template(): void
    {
        $this->actingAs($this->admin);

        $this->get('/employees/import')->assertOk()->assertSee('Unduh Template');
        $this->get('/employees/template-import')->assertOk();
    }

    public function test_import_massal_pegawai_dari_berkas(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        // buat berkas excel sederhana dengan heading + 1 baris
        $file = \Maatwebsite\Excel\Facades\Excel::raw(
            new \App\Exports\GenericExport(
                collect([[
                    'Import Tester', '199001012000031001', 'L', 'Jakarta', '1990-01-01', 'Islam',
                    '', '', '', 'PNS', '', 'S1', '', 'Analis', '', '', '', '', '', '', '', '', '', '', '', 1,
                ]]),
                [
                    'nama', 'nip', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
                    'email', 'telepon', 'alamat', 'status_kode', 'golongan_kode', 'pendidikan_kode',
                    'unit_kode', 'jabatan', 'eselon', 'level_fungsional',
                    'tmt_jabatan', 'tmt_golongan', 'tmt_cpns', 'tmt_pns', 'tanggal_pensiun',
                    'npwp', 'karpeg', 'pendidikan_1', 'pendidikan_2', 'pendidikan_3', 'aktif',
                ],
                'Template Pegawai'
            ),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $tmp = storage_path('app/test-import.xlsx');
        file_put_contents($tmp, $file);

        $upload = new \Illuminate\Http\UploadedFile($tmp, 'test-import.xlsx', null, null, true);

        $response = $this->actingAs($this->admin)
            ->post('/employees/import', ['file' => $upload]);

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'nip' => '199001012000031001',
            'name' => 'Import Tester',
        ]);

        unlink($tmp);
    }

    public function test_halaman_utama_render_setelah_login(): void
    {
        $this->actingAs($this->admin);

        $this->get('/dashboard')->assertOk()->assertSee('pageLoader', false);
        $this->get('/home')->assertOk();
        $this->get('/employees')->assertOk();
        $this->get('/employees/create')->assertOk();
        $this->get('/surat')->assertOk();
        $this->get('/arsip')->assertOk();
        $this->get('/arsip-pinjam')->assertOk();
        $this->get('/master/jenis-surat')->assertOk();
        $this->get('/pengguna')->assertOk();
    }

    public function test_logo_dan_favicon_tampil(): void
    {
        // halaman login: logo di panel kiri + kanan + favicon
        $login = $this->get('/login')->assertOk();
        $login->assertSee('images/logo-kementerian.svg');
        $login->assertSee('Kementerian Transmigrasi RI');
        $login->assertSee('rel="icon" type="image/svg+xml"', false);
        $login->assertSee('favicon-32.png');

        // halaman aplikasi: logo header, logo sidebar, favicon
        $page = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $page->assertSee('header-logo');
        $page->assertSee('sidebar');
        $page->assertSee('images/logo-kementerian.svg');
        $page->assertSee('apple-touch-icon.png');
    }

    public function test_cetak_surat_menampilkan_kop_berlogo(): void
    {
        $letter = \App\Models\Letter::where('status', \App\Models\Letter::STATUS_APPROVED)->first();
        $this->assertNotNull($letter, 'Seeder harus menyediakan surat APPROVED');

        $response = $this->actingAs($this->admin)->get("/surat/{$letter->id}/cetak");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertGreaterThan(10000, strlen($response->getContent()), 'PDF kop surat seharusnya berisi logo');
    }

    /* ================= REDIRECT LOGIN PER ROLE ================= */

    public function test_login_pegawai_redirect_ke_home(): void
    {
        $pegawai = User::firstWhere('email', 'pegawai@osdmrb.go.id');
        $this->assertNotNull($pegawai, 'Seeder harus membuat akun pegawai@osdmrb.go.id');

        $this->get('/login');

        $response = $this->post('/login', [
            'email' => 'pegawai@osdmrb.go.id',
            'password' => 'password',
            'captcha' => session('login_captcha'),
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($pegawai);
    }

    public function test_login_biro_sdm_redirect_ke_dashboard(): void
    {
        $sdm = User::firstWhere('email', 'sdm@osdmrb.go.id');
        $this->assertNotNull($sdm, 'Seeder harus membuat akun sdm@osdmrb.go.id');

        $this->get('/login');

        $response = $this->post('/login', [
            'email' => 'sdm@osdmrb.go.id',
            'password' => 'password',
            'captcha' => session('login_captcha'),
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($sdm);
    }

    /* ================= BATASAN AKSES PEGAWAI ================= */

    public function test_pegawai_hanya_bisa_melihat_profil_sendiri(): void
    {
        $pegawai = User::firstWhere('email', 'pegawai@osdmrb.go.id');
        $own = Employee::find($pegawai->employee_id);
        $other = Employee::whereKeyNot($own->id)->first();

        // profil sendiri via menu "Profil Saya"
        $this->actingAs($pegawai)
            ->get('/pegawai/profil')
            ->assertOk()
            ->assertSee($own->name)
            ->assertSee('Profil Saya');

        // sejak 17 Sept 2026: pegawai BOLEH melihat profil pegawai lain (view only)
        $response = $this->actingAs($pegawai)
            ->get("/employees/{$other->id}")
            ->assertOk()
            ->assertSee($other->name);

        // tanpa tombol ubah (view only)
        $this->assertStringNotContainsString(route('employees.edit', $other), $response->getContent());

        // tidak boleh membuka manajemen data pegawai (CRUD khusus admin)
        $this->actingAs($pegawai)->get('/employees')->assertForbidden();
    }

    /* ================= ROLE BIRO SDM ================= */

    public function test_biro_sdm_bisa_akses_dashboard_dan_pegawai(): void
    {
        $sdm = User::firstWhere('email', 'sdm@osdmrb.go.id');

        $this->actingAs($sdm)->get('/dashboard')->assertOk();
        $this->actingAs($sdm)->get('/employees')->assertOk();
        $this->actingAs($sdm)->get('/employees/create')->assertOk();
        $this->actingAs($sdm)->get('/surat')->assertOk();
        $this->actingAs($sdm)->get('/arsip')->assertOk();
        $this->actingAs($sdm)->get('/master/jenis-surat')->assertOk();
    }

    public function test_biro_sdm_master_data_read_only(): void
    {
        $sdm = User::firstWhere('email', 'sdm@osdmrb.go.id');

        // bisa melihat master data (mode hanya lihat)
        $this->actingAs($sdm)
            ->get('/master')
            ->assertOk()
            ->assertSee('Mode Biro SDM (hanya lihat)');

        // tidak boleh menambah / mengubah master data
        $this->actingAs($sdm)->post('/master/units', [
            'code' => 'TEST-SDM', 'name' => 'Unit Test', 'level' => 'LAINNYA',
        ])->assertForbidden();

        // tidak boleh mengelola pengguna
        $this->actingAs($sdm)->get('/pengguna')->assertForbidden();
    }

    public function test_biro_sdm_bisa_verifikasi_dan_menyetujui_surat(): void
    {
        $sdm = User::firstWhere('email', 'sdm@osdmrb.go.id');

        // pastikan ada surat menunggu verifikasi milik pegawai lain
        $letter = \App\Models\Letter::where('status', \App\Models\Letter::STATUS_PENDING)
            ->where('employee_id', '!=', $sdm->employee_id)
            ->first();

        if (! $letter) {
            $letter = \App\Models\Letter::create([
                'letter_type_id' => \App\Models\LetterType::first()->id,
                'employee_id' => Employee::whereKeyNot($sdm->employee_id)->first()->id,
                'subject' => 'Uji Verifikasi Biro SDM',
                'purpose' => 'Keperluan uji',
                'letter_date' => now()->toDateString(),
                'meta' => [],
                'status' => \App\Models\Letter::STATUS_PENDING,
            ]);
        }

        // Biro SDM memverifikasi
        $this->actingAs($sdm)
            ->post("/surat/{$letter->id}/verifikasi")
            ->assertRedirect();

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => \App\Models\Letter::STATUS_VERIFIED,
        ]);

        // lalu menyetujui & menerbitkan nomor
        $this->actingAs($sdm)
            ->post("/surat/{$letter->id}/setujui")
            ->assertRedirect();

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => \App\Models\Letter::STATUS_APPROVED,
        ]);
    }

    /* ================= ICON SIDEBAR ================= */

    public function test_icon_sidebar_manajemen_talenta_dan_struktur_organisasi(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk()
            ->assertSee('bi-stars', false)      // Manajemen Talenta
            ->assertSee('bi-diagram-3', false); // Struktur Organisasi
    }

    /* ================= BAGAN ORGANISASI & LOGIN TERKUNCI ================= */

    public function test_halaman_struktur_organisasi_memuat_legenda_dan_bagan(): void
    {
        $response = $this->actingAs($this->admin)->get('/modul/struktur-organisasi');

        $response->assertOk()
            ->assertSee('Bagan Resmi Kementerian (SOTK)')
            ->assertSee('Bagan Struktur Unit Kerja')
            ->assertSee('org-legend', false)
            ->assertSee('org-tree', false)
            // kontrol minimize (perkecil) / maximize (perbesar) bagan
            ->assertSee('data-zoom-in', false)
            ->assertSee('data-zoom-out', false)
            ->assertSee('images/struktur-organisasi.png', false);
    }

    public function test_halaman_login_terkunci_tanpa_scroll(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('auth-page', false)      // body terkunci (overflow hidden)
            ->assertSee('auth-shell', false);    // kartu menyesuaikan tinggi viewport
    }

    public function test_tombol_minimize_sidebar_ada(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('sidebarMinimize', false)
            ->assertSee('nav-hamburger', false)      // hamburger sejajar logo
            ->assertSee('bi-list', false);
    }
}
