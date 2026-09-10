<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test fitur update 8 September 2026:
 * zona waktu WIB, statistik pengunjung, master kampus (dropdown pendidikan),
 * data kenaikan jabatan di dashboard, OTP resend countdown & reset password.
 */
class UpdateSeptember8Test extends TestCase
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
    }

    /* ================= ZONA WAKTU ================= */

    public function test_zona_waktu_aplikasi_adalah_gmt7(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('+07:00', now()->format('P'));
    }

    /* ================= STATISTIK PENGUNJUNG ================= */

    public function test_statistik_pengunjung_terisi_setelah_login_dan_kunjungan(): void
    {
        // pastikan tidak ada data awal
        AuditLog::query()->delete();

        $this->actingAs($this->admin);

        // kunjungan halaman dashboard (dicatat middleware LogAuditVisit)
        $this->get('/dashboard')->assertOk();

        $stats = AuditLog::visitorStats();

        $this->assertSame(1, $stats['today']);
        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['uniqueToday']);
        $this->assertTrue(AuditLog::visitorActivity()->whereDate('created_at', today())->exists());
    }

    public function test_login_baru_terhitung_sebagai_pengunjung(): void
    {
        AuditLog::query()->delete();

        $this->get('/login');
        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'password',
            'captcha' => session('login_captcha'),
        ]);

        // login sukses tercatat meski tanpa kunjungan halaman lain
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::EVENT_LOGIN,
            'user_id' => $this->admin->id,
        ]);
        $this->assertSame(1, AuditLog::visitorStats()['today']);
    }

    /* ================= MASTER KAMPUS ================= */

    public function test_master_kampus_terisi_dan_bisa_dikelola(): void
    {
        $this->actingAs($this->admin);

        // data awal dari migrasi
        $this->assertDatabaseHas('campuses', ['name' => 'Universitas Diponegoro']);

        // tab kampus tampil di halaman master
        $this->get('/master')->assertOk()->assertSee('Daftar Kampus');

        // tambah kampus baru
        $this->post('/master/kampus', [
            'name' => 'Universitas Testing Baru',
            'city' => 'Jakarta',
            'type' => 'swasta',
            'sort_order' => 999,
        ])->assertSessionHas('success');

        $campus = Campus::where('name', 'Universitas Testing Baru')->first();
        $this->assertNotNull($campus);
        $this->assertSame('Swasta', $campus->type_label);

        // ubah
        $this->put("/master/kampus/{$campus->id}", [
            'name' => 'Universitas Testing Baru',
            'city' => 'Bandung',
            'type' => 'swasta',
            'sort_order' => 999,
        ])->assertSessionHas('success');
        $this->assertSame('Bandung', $campus->fresh()->city);

        // hapus
        $this->delete("/master/kampus/{$campus->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('campuses', ['name' => 'Universitas Testing Baru']);
    }

    public function test_form_pegawai_memakai_dropdown_kampus(): void
    {
        $this->actingAs($this->admin);

        $undip = Campus::where('name', 'Universitas Diponegoro')->first();

        $this->get('/employees/create')
            ->assertOk()
            ->assertSee('Pilih Kampus')
            ->assertSee('Universitas Diponegoro');

        // simpan pegawai dengan pendidikan dari dropdown kampus
        $this->post('/employees', [
            'nip' => '199001012010011001',
            'name' => 'Pegawai Uji Dropdown',
            'gender' => 'L',
            'is_active' => '1',
            'education_1_campus' => (string) $undip->id,
            'education_1_major' => 'S2 Ilmu Hukum',
        ])->assertSessionHas('success');

        $employee = Employee::where('name', 'Pegawai Uji Dropdown')->first();

        $this->assertSame('S2 Ilmu Hukum, Universitas Diponegoro', $employee->education_1);
        $this->assertNull($employee->education_2);

        // form edit: dropdown kampus terpilih otomatis dari data lama
        $this->get("/employees/{$employee->id}/edit")
            ->assertOk()
            ->assertSee('value="'.$undip->id.'" selected', false)
            ->assertSee('S2 Ilmu Hukum');
    }

    public function test_pendidikan_manual_tersimpan_saat_kampus_tidak_ada_di_master(): void
    {
        $this->actingAs($this->admin);

        $this->post('/employees', [
            'nip' => '199001012010011002',
            'name' => 'Pegawai Uji Manual',
            'gender' => 'P',
            'is_active' => '1',
            'education_1_campus' => 'custom',
            'education_1_custom' => 'S3 Manajemen, Sekolah Tinggi Baru',
        ])->assertSessionHas('success');

        $employee = Employee::where('name', 'Pegawai Uji Manual')->first();

        $this->assertSame('S3 Manajemen, Sekolah Tinggi Baru', $employee->education_1);
    }

    /* ================= DASHBOARD KENAIKAN JABATAN ================= */

    public function test_dashboard_menampilkan_data_kenaikan_jabatan(): void
    {
        $this->actingAs($this->admin);

        // siapkan pegawai dengan jadwal kenaikan tahun depan
        Employee::whereKey(Employee::first())->update([
            'next_promotion_date' => now()->addYear()->toDateString(),
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Kenaikan Jabatan/Pangkat')
            ->assertSee('Proyeksi Kenaikan Jabatan/Pangkat')
            ->assertSee('Kenaikan Jabatan/Pangkat Terdekat');

        // endpoint statistik memuat data kenaikan
        $this->getJson('/api/dashboard/statistics')
            ->assertOk()
            ->assertJsonStructure(['promotion' => ['thisYear', 'nextYear', 'dueSoon', 'overdue', 'projection']]);
    }

    public function test_filter_kenaikan_jabatan_pada_daftar_pegawai(): void
    {
        $this->actingAs($this->admin);

        $this->get('/employees?naik=1')
            ->assertOk()
            ->assertSee('Kenaikan jabatan/pangkat');
    }

    /* ================= OTP RESEND ================= */

    public function test_halaman_otp_menampilkan_countdown_dan_tombol_aktif_setelah_menunggu(): void
    {
        // aktifkan OTP lalu login layer 1
        \App\Models\Setting::set('otp_enabled', true);

        $this->get('/login');
        $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'password',
            'captcha' => session('login_captcha'),
        ]);

        $this->get('/login/otp')
            ->assertOk()
            ->assertSee('otpResendBtn')
            ->assertSee('Kirim ulang (', false); // countdown aktif

        // simulasi waktu tunggu habis: tombol harus bisa aktif
        $this->admin->forceFill(['otp_sent_at' => now()->subSeconds(61)])->save();

        $this->get('/login/otp')
            ->assertOk()
            ->assertSee('Kirim ulang kode'); // tanpa countdown -> tombol aktif

        // kirim ulang berhasil membuat kode baru
        $this->post('/login/otp/resend')
            ->assertSessionHas('status', 'Kode OTP baru telah dikirim ke email Anda.');
    }

    /* ================= RESET PASSWORD ================= */

    public function test_reset_password_tidak_diamkan_saat_smtp_belum_diatur(): void
    {
        config()->set('mail.default', 'log');
        \App\Models\Setting::set('smtp_enabled', false);

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $this->admin->email])
            ->assertSessionHasErrors('email');
    }
}
