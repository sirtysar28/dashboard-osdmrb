<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test pembaruan 11 September 2026:
 * 1. KPI dashboard total keseluruhan pegawai (PNS + PPPK aktif & Non PNS)
 * 2. Stat-card-link berada di bawah filter tanpa data dobel
 * 3. Pencarian pegawai pada halaman pengguna (add pegawai)
 * 4. Kemampuan berenang pada Informasi Personal pegawai
 * 5. Pengajuan cuti (bisa diaktifkan / dinonaktifkan)
 * 6. Informasi login demo dihapus dari form login
 */
class UpdateSeptember11Test extends TestCase
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

    /* ================= 1 + 2. DASHBOARD KPI & STAT CARD ================= */

    public function test_dashboard_menampilkan_kpi_total_keseluruhan_pegawai(): void
    {
        $asnActive = Employee::where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)->count();
        $nonAsnActive = Employee::where('is_active', true)
            ->where('employee_type', Employee::TYPE_NON_ASN)->count();

        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertOk()
            // KPI total keseluruhan = ASN aktif + Non ASN
            ->assertSeeText('Total Keseluruhan Pegawai')
            ->assertSee('<h1>'.number_format($asnActive + $nonAsnActive).'</h1>', false)
            // rincian KPI: ASN, PPPK Aktif, Non ASN
            ->assertSeeText('Non ASN')
            ->assertSee('<h1>'.number_format($nonAsnActive).'</h1>', false);
    }

    public function test_stat_card_berada_dibawah_filter_tanpa_data_dobel(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->getContent();

        // urutan: filter -> KPI -> stat-card
        $filterPos = mb_strpos($html, 'filter-card');
        $this->assertNotFalse($filterPos);

        $kpiPos = mb_strpos($html, 'kpi-card');
        $this->assertNotFalse($kpiPos);
        $this->assertGreaterThan($filterPos, $kpiPos, 'KPI harus berada di bawah filter');

        // kartu stat pertama muncul SETELAH filter & KPI
        $statPos = mb_strpos($html, 'class="stat-card-link"');
        $this->assertNotFalse($statPos);
        $this->assertGreaterThan($filterPos, $statPos);
        $this->assertGreaterThan($kpiPos, $statPos);

        // periksa kartu statistik (setelah filter) — bukan menu sidebar
        $html = mb_substr($html, $filterPos);

        // data yang dobel dengan KPI sudah dihapus dari stat-card
        $this->assertStringNotContainsString('<span>Total Pegawai ASN</span>', $html);
        $this->assertStringNotContainsString('<span>Pegawai Non ASN</span>', $html);
        $this->assertStringNotContainsString('<span>Pegawai PPPK</span>', $html);

        // stat-card yang dipertahankan tetap ada
        $this->assertStringContainsString('Jabatan Struktural', $html);
        $this->assertStringContainsString('Jabatan Fungsional', $html);
        $this->assertStringContainsString('Akan Pensiun Tahun Ini', $html);
        $this->assertStringContainsString('Pengunjung Hari Ini', $html);
    }

    /* ================= 3. PENCARIAN PEGAWAI DI HALAMAN PENGGUNA ================= */

    public function test_halaman_pengguna_menyediakan_pencarian_pegawai(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $response = $this->actingAs($this->admin)->get('/pengguna');

        $response->assertOk()
            ->assertSee('employeeSearchInput', false)
            ->assertSee('employeeIdInput', false)
            ->assertSee($employee->name);
    }

    /* ================= 4. KEMAMPUAN BERENANG ================= */

    public function test_kemampuan_berenang_tersimpan_dan_tampil_di_informasi_personal(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        // simpan lewat form ubah pegawai
        $this->actingAs($this->admin)
            ->put("/employees/{$employee->id}", $this->employeePayload($employee, ['swimming_skill' => 'bisa']))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'swimming_skill' => 'bisa']);

        // tampil pada Informasi Personal di halaman detail
        $this->actingAs($this->admin)
            ->get("/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Kemampuan Berenang')
            ->assertSee('Bisa Berenang');
    }

    public function test_form_pegawai_menampilkan_pilihan_kemampuan_berenang(): void
    {
        $this->actingAs($this->admin)
            ->get('/employees/create')
            ->assertOk()
            ->assertSee('name="swimming_skill"', false)
            ->assertSee('Bisa Berenang')
            ->assertSee('Tidak Bisa Berenang');
    }

    /* ================= 5. PENGAJUAN CUTI ================= */

    public function test_menu_cuti_default_nonaktif(): void
    {
        Setting::flushCache();

        $this->assertFalse(Setting::menuVisible('cuti'));

        $this->actingAs($this->admin)
            ->get('/cuti')
            ->assertNotFound();
    }

    public function test_pengajuan_cuti_bisa_diaktifkan_dan_diajukan(): void
    {
        Setting::set('menu_cuti', true);

        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $this->admin->update(['employee_id' => $employee->id]);

        // menu sidebar muncul
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Pengajuan Cuti');

        // form pengajuan tersedia (mengikuti formulir resmi: Bagian I–VI)
        $this->actingAs($this->admin)
            ->get('/cuti/ajukan')
            ->assertOk()
            ->assertSee('DATA PEGAWAI')
            ->assertSee('JENIS CUTI YANG DIAMBIL')
            ->assertSee('ALASAN CUTI')
            ->assertSee('LAMANYA CUTI')
            ->assertSee('CATATAN CUTI')
            ->assertSee('ALAMAT SELAMA MENJALANKAN CUTI');

        // ajukan cuti tahunan 5 hari (admin bertindak untuk pegawai terpilih)
        $this->actingAs($this->admin)
            ->post('/cuti', [
                'employee_id' => $employee->id,
                'type' => LeaveRequest::TYPE_TAHUNAN,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-18',
                'reason' => 'Acara keluarga',
                'address_during_leave' => 'Jl. Kalibata No. 17, Jakarta',
                'phone_during_leave' => '081234567890',
            ])
            ->assertRedirect();

        $leave = LeaveRequest::first();

        $this->assertSame(LeaveRequest::STATUS_PENDING, $leave->status);
        $this->assertSame(5, $leave->total_days);
        $this->assertSame($employee->id, $leave->employee_id);

        // detail pengajuan menampilkan data (formulir Bagian I–VIII)
        $this->actingAs($this->admin)
            ->get("/cuti/{$leave->id}")
            ->assertOk()
            ->assertSee('Cuti Tahunan')
            ->assertSee('Menunggu Verifikasi')
            ->assertSee('Jl. Kalibata No. 17, Jakarta')
            ->assertSee('081234567890')
            ->assertSee('Masa Kerja')
            ->assertSee('Pertimbangan Atasan Langsung')
            ->assertSee('Keputusan Pejabat yang Berwenang');
    }

    public function test_form_cuti_default_pegawai_yang_sedang_login(): void
    {
        Setting::set('menu_cuti', true);

        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $this->admin->update(['employee_id' => $employee->id]);

        // pencarian pegawai tersedia & terisi default pegawai yang login
        $html = $this->actingAs($this->admin)->get('/cuti/ajukan')->getContent();

        $this->assertStringContainsString('id="employeeSearchInput"', $html, 'Form harus memakai pencarian pegawai.');
        $this->assertMatchesRegularExpression(
            '/id="employeeIdInput"\s+value="'.$employee->id.'"/s',
            $html,
            'employee_id tersembunyi harus default ke pegawai yang sedang login.'
        );

        // Bagian I ikut terisi data pegawai yang login
        $this->assertStringContainsString('id="fNama" readonly', $html);
        $this->assertStringContainsString('value="'.$employee->name.'"', $html);

        // submit tanpa employee_id tetap diajukan atas nama sendiri (default pegawai login)
        $this->actingAs($this->admin)
            ->post('/cuti', [
                'type' => LeaveRequest::TYPE_TAHUNAN,
                'start_date' => '2026-09-14',
                'end_date' => '2026-09-14',
                'reason' => 'Urusan keluarga',
            ])
            ->assertRedirect();

        $leave = LeaveRequest::latest('id')->first();
        $this->assertSame($employee->id, $leave->employee_id);
    }

    public function test_workflow_cuti_verifikasi_persetujuan_penolakan(): void
    {
        Setting::set('menu_cuti', true);

        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $this->admin->update(['employee_id' => $employee->id]);
        $this->admin->syncRoles(['super_admin']);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => LeaveRequest::TYPE_SAKIT,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-15',
            'total_days' => 2,
            'reason' => 'Demam',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // daftar pengajuan tampil
        $this->actingAs($this->admin)
            ->get('/cuti')
            ->assertOk()
            ->assertSee('Cuti Sakit');

        // verifikasi
        $this->actingAs($this->admin)
            ->post("/cuti/{$leave->id}/verifikasi")
            ->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_VERIFIED, $leave->status);
        $this->assertNotNull($leave->verified_at);

        // persetujuan akhir
        $this->actingAs($this->admin)
            ->post("/cuti/{$leave->id}/setujui")
            ->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveRequest::STATUS_APPROVED, $leave->status);
        $this->assertNotNull($leave->approved_at);

        // pengajuan lain ditolak (wajib dengan alasan)
        $rejected = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => LeaveRequest::TYPE_CLTN,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-02',
            'total_days' => 2,
            'reason' => 'Keperluan lain',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post("/cuti/{$rejected->id}/tolak", ['note' => 'Bukan waktu yang tepat'])
            ->assertRedirect();

        $this->assertSame(LeaveRequest::STATUS_REJECTED, $rejected->refresh()->status);
        $this->assertSame('Bukan waktu yang tepat', $rejected->note);
    }

    public function test_cuti_bisa_dinonaktifkan_kembali(): void
    {
        Setting::set('menu_cuti', true);
        Setting::set('menu_cuti', false);

        $this->actingAs($this->admin)
            ->get('/cuti')
            ->assertNotFound();
    }

    /* ================= 6. INFO LOGIN DEMO DIHAPUS ================= */

    public function test_form_login_tidak_menampilkan_akun_demo(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertDontSee('auth-demo')
            ->assertDontSee('admin@osdmrb.go.id')
            ->assertDontSee('pegawai@osdmrb.go.id')
            ->assertDontSee('password: <code>password</code>');
    }

    /* ================= HELPERS ================= */

    private function employeePayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'nip' => $employee->nip,
            'name' => $employee->name,
            'gender' => $employee->gender,
            'is_active' => true,
        ], $overrides);
    }
}
