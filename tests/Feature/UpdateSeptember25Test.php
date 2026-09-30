<?php

namespace Tests\Feature;

use App\Imports\NonAsnEmployeesImport;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Smoke test pembaruan 25 September 2026:
 *  1. Filter dashboard mengikuti aturan status efektif — pegawai berstatus
 *     ASN dengan TMT ASN KOSONG dihitung CPNS (filter CPNS menampilkan
 *     data tersebut, kartu ASN hanya menghitung yang TMT ASN terisi).
 *  2. Seluruh pegawai Non ASN berada di Sekretariat Jenderal — kartu Non ASN
 *     berubah mengikuti filter eselon/balai (tidak muncul sama di semua filter).
 *  3. Import / tambah Non ASN otomatis diberi unit Sekretariat Jenderal.
 *  4. Master Data ➜ Unit Kerja memiliki pencarian & filter (eselon, induk).
 *  5. Kop CV: judul "KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA" satu baris.
 */
class UpdateSeptember25Test extends TestCase
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

    /* ================= 1. STATUS EFEKTIF: TMT ASN KOSONG = CPNS ================= */

    public function test_asn_dengan_tmt_asn_kosong_tampil_saat_filter_cpns(): void
    {
        $asnId = EmploymentStatus::where('code', 'ASN')->value('id');
        $cpnsId = EmploymentStatus::where('code', 'CPNS')->value('id');
        $unitId = Unit::where('code', 'OSDMRB')->value('id');

        // ASN dengan TMT ASN terisi (PNS tetap)
        $pns = Employee::create([
            'nip' => 'T25-PNS-001',
            'name' => 'T25 Pns Tetap Satu',
            'gender' => 'L',
            'employee_type' => 'asn',
            'employment_status_id' => $asnId,
            'unit_id' => $unitId,
            'tmt_pns' => '2020-04-01',
            'is_active' => true,
        ]);

        // berstatus ASN namun TMT ASN masih kosong → efektif CPNS
        $cpnsEfektif = Employee::create([
            'nip' => 'T25-CPNS-002',
            'name' => 'T25 Cpns Efektif Dua',
            'gender' => 'P',
            'employee_type' => 'asn',
            'employment_status_id' => $asnId,
            'unit_id' => $unitId,
            'tmt_pns' => null,
            'is_active' => true,
        ]);

        // status CPNS bawaan
        Employee::create([
            'nip' => 'T25-CPNS-003',
            'name' => 'T25 Cpns Asli Tiga',
            'gender' => 'L',
            'employee_type' => 'asn',
            'employment_status_id' => $cpnsId,
            'unit_id' => $unitId,
            'tmt_pns' => null,
            'is_active' => true,
        ]);

        // ---- filter dashboard status CPNS menampilkan keduanya ----
        $this->actingAs($this->admin)
            ->get('/dashboard?status_asn%5B%5D='.$cpnsId.'&search='.urlencode('T25 '))
            ->assertOk()
            ->assertSeeText('T25 Cpns Efektif Dua')
            ->assertSeeText('T25 Cpns Asli Tiga')
            ->assertDontSeeText('T25 Pns Tetap Satu');

        // ---- filter ASN hanya menampilkan yang TMT ASN terisi ----
        $this->actingAs($this->admin)
            ->get('/dashboard?status_asn%5B%5D='.$asnId.'&search='.urlencode('T25 '))
            ->assertOk()
            ->assertSeeText('T25 Pns Tetap Satu')
            ->assertDontSeeText('T25 Cpns Efektif Dua')
            ->assertDontSeeText('T25 Cpns Asli Tiga');

        // ---- halaman daftar pegawai konsisten ----
        $this->actingAs($this->admin)
            ->get('/employees?status%5B%5D='.$cpnsId.'&search='.urlencode('T25 '))
            ->assertOk()
            ->assertSeeText('T25 Cpns Efektif Dua')
            ->assertDontSeeText('T25 Pns Tetap Satu');

        $this->actingAs($this->admin)
            ->get('/employees?status%5B%5D='.$asnId.'&search='.urlencode('T25 '))
            ->assertOk()
            ->assertSeeText('T25 Pns Tetap Satu')
            ->assertDontSeeText('T25 Cpns Efektif Dua');

        // ---- KPI & infografis: ASN = PNS tetap, ASN tanpa TMT terhitung CPNS ----
        $service = app(DashboardService::class);
        $filtered = ['search' => 'T25 '];
        $query = $service->applyFilters($service->baseQuery(), $filtered);
        $summary = $service->getSummary($query);

        $this->assertSame(1, $summary['asnStatusCount']);
        $this->assertSame(2, $summary['cpnsCount']);

        $composition = collect($service->getStatusComposition($service->applyFilters($service->baseQuery(), $filtered)));
        $this->assertSame(2, (int) $composition->firstWhere('label', 'CPNS')['total']);

        // ---- profil & status tampilan ----
        $this->assertSame('CPNS', $cpnsEfektif->refresh()->display_status);
        $this->assertTrue($cpnsEfektif->is_effective_cpns);
        $this->assertSame('ASN', $pns->refresh()->display_status);

        $this->actingAs($this->admin)
            ->get('/employees/'.$cpnsEfektif->id)
            ->assertOk()
            ->assertSeeText('CPNS');
    }

    public function test_pppk_tetap_dihitung_pppk_meski_tmt_asn_kosong(): void
    {
        $pppkId = EmploymentStatus::where('code', 'PPPK_PENUH')->value('id');

        Employee::create([
            'nip' => 'T25-PPPK-001',
            'name' => 'T25 Pppk Penuh Satu',
            'gender' => 'L',
            'employee_type' => 'asn',
            'employment_status_id' => $pppkId,
            'unit_id' => Unit::where('code', 'OSDMRB')->value('id'),
            'tmt_pns' => null,
            'is_active' => true,
        ]);

        $service = app(DashboardService::class);
        $query = $service->applyFilters($service->baseQuery(), ['search' => 'T25 ']);
        $summary = $service->getSummary($query);

        $this->assertSame(1, $summary['pppkCount']);
        $this->assertSame(0, $summary['cpnsCount']);
    }

    /* ================= 2-3. NON ASN DI SEKRETARIAT JENDERAL ================= */

    public function test_migration_memindahkan_seluruh_non_asn_ke_sekretariat_jenderal(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->first();
        $balai = Unit::where('level', 'BALAI')->first();

        // Non ASN sempat tertempel di balai & tanpa unit (kasus produksi)
        Employee::create(['nip' => 'T25-NONASN-1', 'name' => 'Non Asn Di Balai', 'gender' => 'L',
            'employee_type' => 'non_asn', 'category' => 'Security', 'unit_id' => $balai->id, 'is_active' => true]);
        Employee::create(['nip' => 'T25-NONASN-2', 'name' => 'Non Asn Tanpa Unit', 'gender' => 'P',
            'employee_type' => 'non_asn', 'category' => 'Pramubakti', 'unit_id' => null, 'is_active' => true]);

        $migration = '2026_09_25_000001_force_non_asn_to_setjen';
        DB::table('migrations')->where('migration', $migration)->delete();
        Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

        $this->assertSame(0, Employee::where('employee_type', 'non_asn')->where('unit_id', '!=', $setjen->id)->count());
        $this->assertSame(2, Employee::where('employee_type', 'non_asn')->where('unit_id', $setjen->id)->count());
    }

    public function test_kartu_non_asn_berubah_mengikuti_filter_eselon(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->first();
        $itjen = Unit::where('code', 'ITJEN')->first();

        Employee::where('employee_type', 'non_asn')->delete();
        Employee::create(['nip' => 'T25-SETJEN-SEC', 'name' => 'Security Setjen', 'gender' => 'L',
            'employee_type' => 'non_asn', 'category' => 'Security', 'unit_id' => $setjen->id, 'is_active' => true]);
        // Non ASN tanpa unit → dianggap di Sekretariat Jenderal
        Employee::create(['nip' => 'T25-NULL-CS', 'name' => 'CS Tanpa Unit', 'gender' => 'P',
            'employee_type' => 'non_asn', 'category' => 'Cleaning Service', 'unit_id' => null, 'is_active' => true]);

        $service = app(DashboardService::class);

        $summarySetjen = $service->getSummary(
            $service->applyFilters($service->baseQuery(), ['es1' => [$setjen->id]]),
            $service->nonAsnQuery(['es1' => [$setjen->id]])
        );
        $summaryItjen = $service->getSummary(
            $service->applyFilters($service->baseQuery(), ['es1' => [$itjen->id]]),
            $service->nonAsnQuery(['es1' => [$itjen->id]])
        );

        // kartu Non ASN berubah ketika filter diganti (tidak lagi sama di semua filter)
        $this->assertSame(2, $summarySetjen['nonAsnCount']);
        $this->assertSame(0, $summaryItjen['nonAsnCount']);
        $this->assertNotSame($summarySetjen['nonAsnCount'], $summaryItjen['nonAsnCount']);
    }

    public function test_import_non_asn_otomatis_diberi_unit_sekretariat_jenderal(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->value('id');

        // berkas kecil bergaya daftar Security: NO | NAMA
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['DAFTAR PERSONIL SECURITY'], [], ['NO', 'NAMA'], [1, 'Ujang Import'], [2, 'Encep Import']], null, 'A1');

        $path = storage_path('app/test-non-asn-import-sept25.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        $result = (new NonAsnEmployeesImport('Security'))->import($path, 'security.xlsx');
        unlink($path);

        $this->assertSame(2, $result['created']);

        $ujang = Employee::where('name', 'Ujang Import')->first();
        $this->assertNotNull($ujang);
        $this->assertSame($setjen, $ujang->unit_id);
    }

    public function test_tambah_non_asn_lewat_form_diberi_unit_setjen(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->value('id');

        $this->actingAs($this->admin)
            ->post('/employees/non-asn/create', [
                'name' => 'Pramubakti Form Baru',
                'category' => 'Pramubakti',
                'gender' => 'L',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'name' => 'Pramubakti Form Baru',
            'unit_id' => $setjen,
        ]);
    }

    /* ================= 4. FILTER MASTER DATA UNIT KERJA ================= */

    public function test_master_unit_kerja_bisa_dicari_dan_difilter(): void
    {
        $osdmrb = Unit::where('code', 'OSDMRB')->first();
        $balai = Unit::where('level', 'BALAI')->first();
        $itjen = Unit::where('code', 'ITJEN')->first();
        $setItjen = Unit::where('code', 'SET-ITJEN')->first();

        // pencarian nama
        $response = $this->actingAs($this->admin)
            ->get('/master?tab=units&unit_q='.urlencode('OSDMRB'))
            ->assertOk();
        $response->assertViewHas('units', fn ($units) => $units->total() === 1
            && $units->first()->is($osdmrb));

        // pencarian kode
        $response = $this->actingAs($this->admin)
            ->get('/master?tab=units&unit_q='.urlencode($balai->code))
            ->assertOk();
        $response->assertViewHas('units', fn ($units) => $units->total() === 1
            && $units->first()->is($balai));

        // filter level / eselon — hanya unit Balai
        $response = $this->actingAs($this->admin)
            ->get('/master?tab=units&unit_level=BALAI')
            ->assertOk();
        $response->assertViewHas('units', fn ($units) => $units->total() === Unit::where('level', 'BALAI')->count()
            && $units->every(fn ($u) => $u->level === 'BALAI'));

        // filter induk unit — hanya anak langsung Inspektorat Jenderal
        $response = $this->actingAs($this->admin)
            ->get('/master?tab=units&unit_parent='.$itjen->id)
            ->assertOk();
        $response->assertViewHas('units', fn ($units) => $units->contains($setItjen)
            && $units->every(fn ($u) => $u->parent_id === $itjen->id));
    }

    /* ================= 5. KOP CV SATU BARIS ================= */

    public function test_cv_kop_judul_kementerian_satu_baris(): void
    {
        $employee = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)->first();

        $view = view('employees.cv', ['employee' => $employee->load(
            ['unit', 'education', 'rank', 'employmentStatus',
                'positions.position', 'positions.unit', 'trainings',
                'rankHistories.oldRank', 'rankHistories.newRank']
        )])->render();

        // judul kop tidak turun ke baris kedua: nowrap + ukuran muat satu baris
        $this->assertStringContainsString('white-space: nowrap', $view);
        $this->assertStringContainsString('<h1>KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA</h1>', $view);
        $this->assertStringNotContainsString('KEMENTERIAN TRANSMIGRASI<br', $view);
    }
}
