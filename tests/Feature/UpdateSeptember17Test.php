<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeRankHistory;
use App\Models\EmployeeTraining;
use App\Models\EmploymentStatus;
use App\Models\Rank;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test pembaruan 17 September 2026:
 *  1. Penamaan konsisten PNS -> ASN (master status + tampilan dashboard)
 *  2. Grafik kemampuan berenang pada dashboard
 *  3. Chart KGB (kenaikan gaji berkala 2 tahun) pada dashboard
 *  4. Kolom kemampuan Bahasa Inggris pada data personal
 *  5. Data seminar/pelatihan dalam & luar negeri pada profil pegawai
 *  6. Unduh CV pegawai (PDF)
 *  7. Menu Analisis Jabatan Pelaksana
 *  8. Cetak formulir cuti ke PDF
 *  9. Riwayat diklat/pelatihan dapat ditambahkan pegawai sendiri
 * 10. Riwayat kenaikan pangkat (III/a -> III/b)
 * 11. Pegawai dapat melihat & mencari pegawai lain (view only)
 * 12. Filter lebih dari satu (status multi-select)
 */
class UpdateSeptember17Test extends TestCase
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

        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $this->pegawai = User::firstWhere('email', 'pegawai@osdmrb.go.id')
            ?: User::create([
                'name' => $employee->name,
                'email' => 'pegawai@osdmrb.go.id',
                'password' => Hash::make('password'),
                'employee_id' => $employee->id,
            ]);
        $this->pegawai->syncRoles(['pegawai']);
    }

    /* ================= 1. PNS -> ASN ================= */

    public function test_status_kepegawaian_pns_diganti_asn(): void
    {
        $this->assertDatabaseHas('employment_statuses', ['code' => 'ASN', 'name' => 'ASN']);
        $this->assertDatabaseMissing('employment_statuses', ['code' => 'PNS']);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('ASN')
            ->assertDontSeeText('PNS + PPPK aktif');
    }

    /* ================= 2. GRAFIK KEMAMPUAN BERENANG ================= */

    public function test_dashboard_menampilkan_grafik_kemampuan_berenang(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kemampuan Berenang')
            ->assertSee('swimmingChart', false);
    }

    /* ================= 3. CHART KGB ================= */

    public function test_dashboard_menampilkan_chart_kgb_dan_perhitungan_2_tahun(): void
    {
        Employee::query()->where('employee_type', '!=', Employee::TYPE_NON_ASN)->update([
            'tmt_golongan' => now()->subMonths(6)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Kenaikan Gaji Berkala')
            ->assertSee('salaryRaiseChart', false);

        // KGB berikutnya = TMT golongan + 2 tahun
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $this->assertEquals(
            $employee->tmt_golongan->copy()->addYears(2)->toDateString(),
            $employee->next_salary_raise->toDateString(),
        );

        // filter daftar pegawai by KGB
        $this->actingAs($this->admin)
            ->get('/employees?kgb=2')
            ->assertOk()
            ->assertSee($employee->name);
    }

    /* ================= 4. KEMAMPUAN BAHASA INGGRIS ================= */

    public function test_kemampuan_bahasa_inggris_tersimpan_dan_tampil(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $this->actingAs($this->admin)
            ->put("/employees/{$employee->id}", $this->employeePayload($employee, ['english_skill' => 'menengah']))
            ->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'english_skill' => 'menengah']);

        $this->actingAs($this->admin)
            ->get("/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Kemampuan Bahasa Inggris')
            ->assertSee('Menengah');
    }

    /* ================= 5. SEMINAR / PELATIHAN LUAR NEGERI ================= */

    public function test_seminar_luar_negeri_tersimpan_dan_tampil_di_profil(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $this->actingAs($this->admin)
            ->post('/modul/diklat', [
                'employee_id' => $employee->id,
                'name' => 'International Seminar on Public Administration',
                'type' => 'SEMINAR',
                'scope' => 'LUAR_NEGERI',
                'organizer' => 'UNESCO',
                'year' => now()->year,
                'from' => 'profile',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get("/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('International Seminar on Public Administration')
            ->assertSee('Luar Negeri');
    }

    /* ================= 6. UNDUH CV ================= */

    public function test_cv_pegawai_bisa_diunduh_pdf(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $this->actingAs($this->admin)
            ->get("/employees/{$employee->id}/cv")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /* ================= 7. MENU ANALISIS JABATAN PELAKSANA ================= */

    public function test_menu_analisis_jabatan_pelaksana_tersedia(): void
    {
        $this->actingAs($this->admin)
            ->get('/modul/analisis-jabatan-pelaksana')
            ->assertOk()
            ->assertSee('Analisis Jabatan Pelaksana')
            ->assertSee('Daftar Jabatan Pelaksana');
    }

    /* ================= 8. CETAK FORMULIR CUTI PDF ================= */

    public function test_formulir_cuti_bisa_dicetak_pdf(): void
    {
        Setting::set('menu_cuti', true);

        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $this->actingAs($this->pegawai)
            ->post('/cuti', [
                'type' => 'tahunan',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'reason' => 'Uji cetak PDF',
            ])
            ->assertRedirect();

        $leave = \App\Models\LeaveRequest::first();

        $this->actingAs($this->pegawai)
            ->get("/cuti/{$leave->id}/cetak")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /* ================= 9. PEGAWAI TAMBAH RIWAYAT DIKLAT SENDIRI ================= */

    public function test_pegawai_bisa_menambah_riwayat_diklat_sendiri(): void
    {
        $employeeId = $this->pegawai->employee_id;

        $this->actingAs($this->pegawai)
            ->post('/modul/diklat', [
                'employee_id' => $employeeId,
                'name' => 'Diklat Teknis Kepegawaian',
                'type' => 'TEKNIS',
                'scope' => 'DALAM_NEGERI',
                'year' => now()->year,
                'from' => 'profile',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employee_trainings', [
            'employee_id' => $employeeId,
            'name' => 'Diklat Teknis Kepegawaian',
        ]);

        // profil sendiri menampilkan riwayat
        $this->actingAs($this->pegawai)
            ->get('/pegawai/profil')
            ->assertOk()
            ->assertSee('Riwayat Diklat, Seminar & Pelatihan')
            ->assertSee('Diklat Teknis Kepegawaian');
    }

    public function test_pegawai_tidak_bisa_menambah_riwayat_pegawai_lain(): void
    {
        $other = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->where('id', '!=', $this->pegawai->employee_id)->first();

        $this->actingAs($this->pegawai)
            ->post('/modul/diklat', [
                'employee_id' => $other->id,
                'name' => 'Diklat ilegal',
                'type' => 'TEKNIS',
            ])
            ->assertForbidden();
    }

    /* ================= 10. RIWAYAT KENAIKAN PANGKAT ================= */

    public function test_riwayat_kenaikan_pangkat_tercatat_dan_tampil(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $oldRank = Rank::where('code', 'III/a')->first();
        $newRank = Rank::where('code', 'III/b')->first();

        $this->actingAs($this->admin)
            ->post("/employees/{$employee->id}/riwayat-pangkat", [
                'old_rank_id' => $oldRank->id,
                'new_rank_id' => $newRank->id,
                'sk_number' => 'SK-999/2026',
                'effective_date' => '2026-10-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employee_rank_histories', [
            'employee_id' => $employee->id,
            'new_rank_id' => $newRank->id,
            'sk_number' => 'SK-999/2026',
        ]);

        $this->actingAs($this->admin)
            ->get("/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Riwayat Kenaikan Pangkat')
            ->assertSee('III/a → III/b');
    }

    public function test_ubah_golongan_mencatat_riwayat_otomatis(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();
        $oldRankId = $employee->rank_id;
        $newRank = Rank::where('id', '!=', $oldRankId)->where('code', 'III/b')->first()
            ?? Rank::where('id', '!=', $oldRankId)->first();

        $this->actingAs($this->admin)
            ->put("/employees/{$employee->id}", $this->employeePayload($employee, ['rank_id' => $newRank->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('employee_rank_histories', [
            'employee_id' => $employee->id,
            'old_rank_id' => $oldRankId,
            'new_rank_id' => $newRank->id,
        ]);
    }

    /* ================= 11. DIREKTORI PEGAWAI (VIEW ONLY) ================= */

    public function test_pegawai_bisa_melihat_dan_mencari_pegawai_lain(): void
    {
        $other = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->where('id', '!=', $this->pegawai->employee_id)->first();

        // direktori tampil & bisa mencari
        $this->actingAs($this->pegawai)
            ->get('/direktori-pegawai?search='.urlencode($other->name))
            ->assertOk()
            ->assertSee('Direktori Pegawai')
            ->assertSee($other->name);

        // boleh membuka profil pegawai lain (view only — tanpa tombol ubah)
        $response = $this->actingAs($this->pegawai)
            ->get("/employees/{$other->id}")
            ->assertOk()
            ->assertSee($other->name);

        $this->assertStringNotContainsString(route('employees.edit', $other), $response->getContent());

        // pegawai tetap tidak bisa mengubah data pegawai lain
        $this->actingAs($this->pegawai)
            ->get("/employees/{$other->id}/edit")
            ->assertForbidden();
    }

    /* ================= 12. FILTER LEBIH DARI SATU ================= */

    public function test_filter_status_lebih_dari_satu(): void
    {
        $asnId = EmploymentStatus::where('code', 'ASN')->first()->id;
        $pppkId = EmploymentStatus::where('code', 'PPPK_PENUH')->first()->id;

        // pilih 2 status sekaligus
        $response = $this->actingAs($this->admin)
            ->get('/employees?status[]='.$asnId.'&status[]='.$pppkId)
            ->assertOk();

        // jumlah baris hasil filter harus sama dgn query manual (multi status)
        $expected = Employee::whereIn('employment_status_id', [$asnId, $pppkId])
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->count();

        $response->assertSee('Daftar Pegawai ('.$expected.')');

        // nama pada halaman pertama tampil semua
        $firstPage = Employee::whereIn('employment_status_id', [$asnId, $pppkId])
            ->where('is_active', true)
            ->where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->orderBy('name')
            ->limit(15)
            ->pluck('name');

        foreach ($firstPage as $name) {
            $response->assertSee($name);
        }
    }

    /* ================= HELPER ================= */

    private function employeePayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'nip' => $employee->nip,
            'name' => $employee->name,
            'gender' => $employee->gender,
            'employee_type' => $employee->employee_type ?? 'asn',
            'email' => $employee->email,
            'phone' => $employee->phone,
            'religion' => $employee->religion,
            'swimming_skill' => $employee->swimming_skill,
            'employment_status_id' => $employee->employment_status_id,
            'rank_id' => $employee->rank_id,
            'education_level_id' => $employee->education_level_id,
            'unit_id' => $employee->unit_id,
            'is_active' => true,
        ], $overrides);
    }
}
