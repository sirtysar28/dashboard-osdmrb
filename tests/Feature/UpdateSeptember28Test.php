<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\LeaveRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\JabatanSyncService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test pembaruan 28 September 2026 (Catatan Masukan 28 Sept):
 *  1. Hasil filter menu ASN dan Direktori Pegawai SAMA — pegawai berstatus
 *     ASN dengan TMT ASN kosong (dihitung CPNS) tampil di keduanya saat
 *     difilter CPNS.
 *  2. Analis Jabatan Fungsional mengidentifikasi SEMUA nama jabatan
 *     fungsional dari data pegawai (bukan hanya 10 jenis di master).
 *  3. Analis Jabatan Struktural menampilkan pimpinan (Terisi) di tiap
 *     jabatan, bukan kosong semua.
 *  4. Kop formulir permohonan cuti sesuai format resmi: alamat & situs web
 *     baris terpisah, "www.kemendesa.go.id" (bukan kemendes).
 *  5. Versi aplikasi tampil di footer (config app.version).
 */
class UpdateSeptember28Test extends TestCase
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
        $this->admin->syncRoles(['admin']);
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'nip' => '19'.str_pad((string) random_int(1, 999999999), 9, '0'),
            // nama diawali "Aaaa" agar selalu muncul di halaman pertama paginasi
            'name' => 'Aaaa Pegawai Uji '.uniqid(),
            'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
            'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
            'employee_type' => Employee::TYPE_ASN,
            'is_active' => true,
        ], $attributes));
    }

    /* ======== 1. FILTER CPNS: MENU ASN DAN DIREKTORI SAMA ======== */

    public function test_filter_cpns_tampil_sama_di_menu_asn_dan_direktori(): void
    {
        $cpnsId = EmploymentStatus::where('code', 'CPNS')->value('id');
        $asnId = EmploymentStatus::where('code', 'ASN')->value('id');

        // pegawai berstatus ASN namun TMT ASN kosong → dihitung CPNS
        $employee = $this->makeEmployee([
            'employment_status_id' => $asnId,
            'tmt_pns' => null,
        ]);

        // hasil kedua menu harus SAMA: sama-sama menampilkan pegawai tersebut
        $this->actingAs($this->admin)
            ->get(route('employees.index', ['status' => [$cpnsId], 'search' => $employee->nip]))
            ->assertOk()
            ->assertSee($employee->name);

        $this->actingAs($this->admin)
            ->get(route('employees.directory', ['status' => [$cpnsId], 'search' => $employee->nip]))
            ->assertOk()
            ->assertSee($employee->name);
    }

    public function test_filter_asn_hanya_yang_tmt_terisi_di_dua_menu(): void
    {
        $asnId = EmploymentStatus::where('code', 'ASN')->value('id');

        $tanpaTmt = $this->makeEmployee(['employment_status_id' => $asnId, 'tmt_pns' => null]);
        $denganTmt = $this->makeEmployee(['employment_status_id' => $asnId, 'tmt_pns' => '2020-01-01']);

        foreach (['employees.index', 'employees.directory'] as $route) {
            // pencarian "Aaaa Pegawai Uji" hanya menampilkan kedua pegawai uji ini
            $res = $this->actingAs($this->admin)
                ->get(route($route, ['status' => [$asnId], 'search' => 'Aaaa Pegawai Uji']))
                ->assertOk();

            // filter ASN → hanya yang TMT ASN-nya terisi (aturan sama di dua menu)
            $res->assertSee($denganTmt->name);
            $res->assertDontSee($tanpaTmt->name);
        }
    }

    /* ======== 2. ANALIS JABATAN FUNGSIONAL: SEMUA JABATAN DATA PEGAWAI ======== */

    public function test_analis_jabatan_fungsional_mengenali_semua_jabatan_dari_data(): void
    {
        // jabatan fungsional yang TIDAK ada di master (master hanya ~10 jenis)
        $this->makeEmployee([
            'position_name' => 'Analis Kepegawaian Ahli Muda',
            'functional_level' => 'Ahli Muda',
            'eselon' => null,
            'tmt_pns' => '2020-01-01',
        ]);

        // master tidak punya jabatan tersebut sebelum sinkronisasi
        $this->assertDatabaseMissing('positions', ['name' => 'Analis Kepegawaian Ahli Muda']);

        $res = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan'))
            ->assertOk();

        $res->assertSee('Analis Kepegawaian Ahli Muda');
        $res->assertSee('Terisi');

        // master jabatan ikut terisi lewat sinkronisasi (idempoten)
        $this->assertDatabaseHas('positions', ['name' => 'Analis Kepegawaian Ahli Muda']);
    }

    /* ======== 3. ANALIS JABATAN STRUKTURAL: PIMPINAN TERISI ======== */

    public function test_analis_jabatan_struktural_menampilkan_pimpinan(): void
    {
        $kabag = $this->makeEmployee([
            'name' => 'Aaaa Kepala Bagian Uji Struktural',
            'position_name' => 'Kepala Bagian Perencanaan dan Data',
            'eselon' => 'III',
            'functional_level' => null,
            'tmt_pns' => '2018-01-01',
        ]);

        // jabatan umum lain yang belum ada pemangkunya — agar nama pejabat uji
        // pasti tampil pada daftar 3 nama pemangku pertama
        $direktur = $this->makeEmployee([
            'name' => 'Aaaa Direktur Uji Struktural',
            'position_name' => 'Direktur Pembangunan Kawasan Transmigrasi',
            'eselon' => 'II',
            'functional_level' => null,
            'tmt_pns' => '2018-01-01',
        ]);

        $res = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan-struktural'))
            ->assertOk();

        // Catatan 30 Sept 2026: halaman struktural memakai NAMA JABATAN UMUM —
        // "Kepala Bagian Perencanaan dan Data" dikelompokkan sebagai "Kepala Bagian"…
        $res->assertSee('Kepala Bagian');
        $res->assertDontSee('Kepala Bagian Perencanaan dan Data');
        // …beserta pimpinannya (status Terisi, bukan kosong semua)
        $res->assertSee($direktur->name);
        $res->assertSee('Terisi');
    }

    public function test_sinkronisasi_jabatan_idempoten(): void
    {
        $this->makeEmployee([
            'position_name' => 'Auditor Ahli Madya',
            'functional_level' => 'Ahli Madya',
            'eselon' => null,
        ]);

        JabatanSyncService::syncFromEmployees();
        $first = \App\Models\Position::where('name', 'Auditor Ahli Madya')->count();
        $linked = \App\Models\EmployeePosition::where('is_current', true)->count();

        JabatanSyncService::syncFromEmployees();
        JabatanSyncService::syncFromEmployees();

        $this->assertSame(1, $first);
        $this->assertSame($linked, \App\Models\EmployeePosition::where('is_current', true)->count());
    }

    /* ======== 4. KOP FORMULIR CUTI SESUAI FORMAT RESMI ======== */

    public function test_kop_formulir_cuti_sesuai_format_resmi(): void
    {
        config(['menu_cuti' => true]);

        $employee = $this->makeEmployee(['tmt_pns' => '2020-01-01']);

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => LeaveRequest::TYPE_TAHUNAN,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 3,
            'reason' => 'Keperluan keluarga',
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $html = view('cuti.pdf', ['leave' => $leave->load('employee.unit')])->render();

        $this->assertStringContainsString('www.kemendesa.go.id', $html);
        $this->assertStringNotContainsString('kemendes.go.id', $html);
        // alamat & situs web baris terpisah (format resmi FORM CUTI KOSONG — ASN)
        $this->assertStringContainsString('PO BOX 70 JKS PM/KBY</p>', $html);
        $this->assertStringContainsString('<p>www.kemendesa.go.id</p>', $html);
    }

    /* ======== 5. VERSI APLIKASI DI FOOTER ======== */

    public function test_versi_aplikasi_tampil_di_footer(): void
    {
        $res = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $res->assertSee('Dashboard v'.config('app.version'));
    }
}
