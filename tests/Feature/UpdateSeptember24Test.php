<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test pembaruan 24 September 2026 (catatan rapat 23 Sept REV):
 *  1. Tanpa kata "aktif" pada nama ASN & PPPK (kartu KPI dashboard)
 *  2. User pegawai tidak bisa melihat Alamat & No. HP pegawai lain
 *  3. Unduh CV hanya pegawai bersangkutan atau admin
 *  4. Judul CV "Transmigrasi" + logo Kementerian, tanpa subjudul Biro
 *  5. Kop surat & formulir cuti: "TRANSMIGRASI" (bukan Transigrasi)
 *  6. Pencarian nama pegawai di Direktori Pegawai berfungsi
 *  7. Jumlah Non ASN mengikuti filter unit (berbeda per eselon/balai)
 *  8. Struktur unit kerja: Eselon I tepat 4 (tidak dobel)
 *  9. Non ASN tanpa unit kerja dimasukkan ke Sekretariat Jenderal
 */
class UpdateSeptember24Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private Employee $employee; // milik $this->pegawai

    private Employee $other; // pegawai lain

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

        [$this->employee, $this->other] = Employee::where('employee_type', '!=', Employee::TYPE_NON_ASN)
            ->orderBy('id')
            ->take(2)
            ->get();

        $this->other->update([
            'phone' => '081299887766',
            'address' => 'Jl. Rahasia Pegawai No. 99',
        ]);

        // pakai akun pegawai bawaan seeder lalu pastikan terhubung ke employee uji
        $this->pegawai = User::firstWhere('email', 'pegawai@osdmrb.go.id')
            ?: User::create([
                'name' => $this->employee->name,
                'email' => 'pegawai@osdmrb.go.id',
                'password' => Hash::make('password'),
                'employee_id' => $this->employee->id,
            ]);
        $this->pegawai->syncRoles(['pegawai']);
        $this->pegawai->forceFill([
            'employee_id' => $this->employee->id,
            'name' => $this->employee->name,
        ])->save();
    }

    /* ================= 1. TANPA KATA "AKTIF" ================= */

    public function test_dashboard_tanpa_kata_aktif_pada_nama_asn_dan_pppk(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText('PPPK Aktif')
            ->assertDontSeeText('berstatus ASN aktif')
            ->assertDontSeeText('PPPK aktif + Non ASN')
            ->assertSeeText('ASN & PPPK + Non ASN');
    }

    /* ================= 2. DATA PRIBADI DISEMBUNYIKAN ================= */

    public function test_pegawai_tidak_bisa_lihat_no_hp_dan_alamat_pegawai_lain(): void
    {
        $this->actingAs($this->pegawai)
            ->get("/employees/{$this->other->id}")
            ->assertOk()
            ->assertDontSeeText('081299887766')
            ->assertDontSeeText('Jl. Rahasia Pegawai No. 99')
            ->assertSeeText('disembunyikan — hanya admin');
    }

    public function test_admin_bisa_lihat_no_hp_dan_alamat_pegawai(): void
    {
        $this->actingAs($this->admin)
            ->get("/employees/{$this->other->id}")
            ->assertOk()
            ->assertSeeText('081299887766')
            ->assertSeeText('Jl. Rahasia Pegawai No. 99');
    }

    public function test_pegawai_tetap_bisa_lihat_no_hp_dan_alamat_sendiri(): void
    {
        $this->employee->update(['phone' => '081200001111', 'address' => 'Jl. Rumah Sendiri No. 1']);

        $this->actingAs($this->pegawai)
            ->get('/pegawai/profil')
            ->assertOk()
            ->assertSeeText('081200001111')
            ->assertSeeText('Jl. Rumah Sendiri No. 1');
    }

    /* ================= 3. UNDUH CV ================= */

    public function test_pegawai_bisa_unduh_cv_sendiri(): void
    {
        $this->actingAs($this->pegawai)
            ->get("/employees/{$this->employee->id}/cv")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pegawai_tidak_bisa_unduh_cv_pegawai_lain(): void
    {
        $this->actingAs($this->pegawai)
            ->get("/employees/{$this->other->id}/cv")
            ->assertForbidden();
    }

    public function test_admin_bisa_unduh_cv_pegawai_mana_pun(): void
    {
        $this->actingAs($this->admin)
            ->get("/employees/{$this->other->id}/cv")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_tombol_cv_tidak_tampil_di_direktori_bagi_pegawai(): void
    {
        // pakai pencarian supaya baris pegawai lain pasti tampil (bukan di luar halaman 1)
        $url = '/direktori-pegawai?search='.urlencode($this->other->name);

        $this->actingAs($this->pegawai)
            ->get($url)
            ->assertOk()
            ->assertDontSee('employees/'.$this->other->id.'/cv');

        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertSee('employees/'.$this->other->id.'/cv');
    }

    /* ================= 4-5. JUDUL CV & KOP SURAT ================= */

    public function test_cv_menampilkan_transmigrasi_logo_tanpa_subjudul_biro(): void
    {
        $pdf = $this->actingAs($this->admin)
            ->get("/employees/{$this->employee->id}/cv")
            ->assertOk()
            ->getContent();

        // teks judul pada dokumen PDF terkompresi — validasi lewat view sumber
        $view = view('employees.cv', ['employee' => $this->employee->load(
            ['unit', 'education', 'rank', 'employmentStatus',
                'positions.position', 'positions.unit', 'trainings',
                'rankHistories.oldRank', 'rankHistories.newRank']
        )])->render();

        $this->assertStringContainsString('KEMENTERIAN TRANSMIGRASI REPUBLIK INDONESIA', $view);
        $this->assertStringNotContainsString('TRANSIGRASI', strtoupper($view));
        // subjudul Biro pada kop dihilangkan (nama unit kerja pegawai tetap tampil di isi CV)
        $this->assertStringNotContainsString('<p class="unit">', $view);
        $this->assertStringContainsString('logo-kementerian.png', $view);
        $this->assertNotEmpty($pdf);
    }

    public function test_kop_surat_dan_formulir_cuti_memakai_transmigrasi(): void
    {
        $letters = file_get_contents(resource_path('views/letters/pdf.blade.php'));
        $cuti = file_get_contents(resource_path('views/cuti/pdf.blade.php'));

        $this->assertStringNotContainsString('TRANSIGRASI', strtoupper($letters));
        $this->assertStringNotContainsString('TRANSIGRASI', strtoupper($cuti));
        $this->assertStringContainsString('TRANSMIGRASI', strtoupper($letters));
        $this->assertStringContainsString('TRANSMIGRASI', strtoupper($cuti));
    }

    /* ================= 6. PENCARIAN DIREKTORI ================= */

    public function test_pencarian_nama_pegawai_di_direktori_berfungsi(): void
    {
        $keyword = mb_substr($this->other->name, 0, 5);

        $response = $this->actingAs($this->pegawai)
            ->get('/direktori-pegawai?search='.urlencode($keyword))
            ->assertOk()
            ->assertSeeText($this->other->name);

        // jumlah hasil sama dengan query pencarian langsung ke database
        $expected = Employee::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$keyword}%")
                ->orWhere('nip', 'like', "%{$keyword}%"))
            ->count();

        $this->assertGreaterThan(0, $expected);
        $this->assertNotSame(Employee::where('is_active', true)->count(), $expected, 'Pencarian harus memfilter hasil, bukan menampilkan semua pegawai');
        $response->assertSeeText("Daftar Pegawai ({$expected})");
    }

    /* ================= 7. NON ASN IKUT FILTER UNIT ================= */

    public function test_jumlah_non_asn_mengikuti_filter_unit_dashboard(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->first();
        $balai = Unit::where('level', 'BALAI')->first();

        Employee::where('employee_type', Employee::TYPE_NON_ASN)->delete();

        Employee::create([
            'nip' => 'NONASN-SETJEN-1',
            'name' => 'Non ASN Setjen Satu',
            'gender' => 'L',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Security',
            'unit_id' => $setjen->id,
            'is_active' => true,
        ]);
        Employee::create([
            'nip' => 'NONASN-BALAI-2',
            'name' => 'Non ASN Balai Dua',
            'gender' => 'P',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Pramubakti',
            'unit_id' => $balai->id,
            'is_active' => true,
        ]);

        $service = app(DashboardService::class);

        // filter Sekretariat Jenderal + seluruh turunannya (termasuk balai di bawah Setjen)
        $filtersSetjen = ['es1' => [$setjen->id]];
        $summarySetjen = $service->getSummary(
            $service->applyFilters($service->baseQuery(), $filtersSetjen),
            $service->nonAsnQuery($filtersSetjen)
        );

        // filter balai saja — Non ASN Setjen tidak ikut
        $filtersBalai = ['balai' => [$balai->id]];
        $summaryBalai = $service->getSummary(
            $service->applyFilters($service->baseQuery(), $filtersBalai),
            $service->nonAsnQuery($filtersBalai)
        );

        $this->assertSame(2, $summarySetjen['nonAsnCount']); // Setjen + balai (turunan Setjen)
        $this->assertSame(1, $summaryBalai['nonAsnCount']); // hanya Non ASN di balai
        $this->assertNotSame($summarySetjen['nonAsnCount'], $summaryBalai['nonAsnCount']);
    }

    /* ================= 8-9. STRUKTUR ESELON I & NON ASN SETJEN ================= */

    public function test_struktur_organisasi_eselon_satu_tepat_empat_tanpa_dobel(): void
    {
        // buat duplikat seperti kasus produksi: unit Eselon I tambahan
        $kemen = Unit::where('code', 'KEMEN')->first();
        $dup = Unit::create([
            'code' => 'SETJEN-DUP',
            'name' => 'Sekretariat Jenderal', // nama sama, kode beda
            'level' => 'ES_I',
            'parent_id' => $kemen?->id,
        ]);
        Employee::create([
            'nip' => 'DUP-EMP-001',
            'name' => 'Pegawai Di Unit Duplikat',
            'gender' => 'L',
            'employee_type' => 'asn',
            'unit_id' => $dup->id,
            'is_active' => true,
        ]);

        $this->assertSame(5, Unit::where('level', 'ES_I')->count());

        // jalankan ulang migration pembersih (idempoten)
        $migration = '2026_09_24_000001_update_features_september_24';
        DB::table('migrations')->where('migration', $migration)->delete();
        Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

        // total tepat 4, duplikat hilang, relasi pegawai tersambung ke SETJEN
        $this->assertSame(4, Unit::where('level', 'ES_I')->count());
        $this->assertDatabaseMissing('units', ['code' => 'SETJEN-DUP']);

        $moved = Employee::where('name', 'Pegawai Di Unit Duplikat')->first();
        $this->assertSame(Unit::where('code', 'SETJEN')->value('id'), $moved->unit_id);

        // halaman bagan tetap tampil & kartu Eselon I bernilai 4
        $this->actingAs($this->admin)
            ->get('/modul/struktur-organisasi')
            ->assertOk()
            ->assertSeeText('Eselon I');
    }

    public function test_non_asn_tanpa_unit_dimasukkan_ke_sekretariat_jenderal(): void
    {
        Employee::create([
            'nip' => 'NONASN-TANPA-UNIT',
            'name' => 'Non ASN Tanpa Unit',
            'gender' => 'L',
            'employee_type' => Employee::TYPE_NON_ASN,
            'category' => 'Cleaning Service',
            'unit_id' => null,
            'is_active' => true,
        ]);

        $migration = '2026_09_24_000001_update_features_september_24';
        DB::table('migrations')->where('migration', $migration)->delete();
        Artisan::call('migrate', ['--path' => "database/migrations/{$migration}.php", '--force' => true]);

        $this->assertSame(
            Unit::where('code', 'SETJEN')->value('id'),
            Employee::where('name', 'Non ASN Tanpa Unit')->value('unit_id')
        );
    }
}
