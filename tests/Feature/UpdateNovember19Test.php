<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\PositionMap;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke test Catatan Masukan 19 November 2026:
 *
 *  1. Data Jabatan Struktural: "Direktur Pengembangan ..." tergabung ke
 *     baris "Direktur" (nama jabatan umum, satu baris).
 *  2. Peta Jabatan: tersedia filter "Unit Kerja (Eselon II)" yang
 *     memfilter tabel sebaran jabatan beserta turunannya.
 *  3. Pengajuan Layanan (menu baru): form + kirim pengajuan (upload Nota
 *     Dinas & link bukti dukung) + daftar pengajuan 10 item per halaman.
 *  4. Data Renang Pegawai (menu baru): filter unit kerja & kemampuan
 *     berenang, card Total/Lulus/Belum Lulus, rekap per unit kerja,
 *     daftar pegawai ASN.
 *  5. Sidebar: "Modul Biro OSDMRB" berganti "Analisis Kepegawaian" dengan
 *     susunan menu baru (Peta Jabatan didahulukan, grup Pendukung, dll.).
 */
class UpdateNovember19Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private Employee $pegawaiEmployee;

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

        $this->pegawaiEmployee = Employee::firstOrCreate(
            ['nip' => '199001012010011909'],
            [
                'name' => 'Pegawai Uji 19 Nov',
                'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
                'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
                'employee_type' => Employee::TYPE_ASN,
                'is_active' => true,
                'tmt_pns' => '2015-01-01',
            ],
        );

        $this->pegawai = User::firstOrCreate(
            ['email' => 'pegawai19@osdmrb.go.id'],
            ['name' => 'Pegawai Uji 19 Nov', 'password' => Hash::make('password'), 'employee_id' => $this->pegawaiEmployee->id],
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

    /* ======== 1. DIREKTUR PENGEMBANGAN TERGABUNG KE DIREKTUR ======== */

    public function test_direktur_pengembangan_tergabung_ke_baris_direktur(): void
    {
        // simulasikan master hasil sinkronisasi 28 Sept di server live:
        // ada baris master bernama LENGKAP "Direktur Pengembangan ..."
        $struktural = \App\Models\PositionType::where('code', 'STRUKTURAL')->firstOrFail();
        \App\Models\Position::create([
            'position_type_id' => $struktural->id,
            'code' => 'JAB-DIREKTUR-PENGEMBANGAN-TRANSMIGRASI',
            'name' => 'Direktur Pengembangan Transmigrasi',
            'description' => 'Simulasi master lama di server live',
        ]);

        $this->makeEmployee(['name' => 'Direktur A', 'position_name' => 'Direktur', 'eselon' => 'II']);
        $this->makeEmployee(['name' => 'Direktur B', 'position_name' => 'Direktur Pengembangan Transmigrasi', 'eselon' => 'II']);
        $this->makeEmployee(['name' => 'Direktur C', 'position_name' => 'Direktur Pengembangan dan Pemberdayaan Masyarakat', 'eselon' => 'II']);

        // jabatan ber-awalan Plt. juga harus tergabung (bukan baris terpisah)
        $this->makeEmployee(['name' => 'Direktur D', 'position_name' => 'Plt. Direktur Pembangunan Kawasan Transmigrasi', 'eselon' => 'II']);

        $response = $this->actingAs($this->admin)->get('/modul/analisis-jabatan-struktural');

        $response->assertOk();

        // hanya ada SATU baris "Direktur" — bukan baris terpisah per direktorat
        $response->assertSee('<td class="fw-semibold">Direktur</td>', false);
        $response->assertDontSee('<td class="fw-semibold">Direktur Pengembangan', false);
        $response->assertDontSee('<td class="fw-semibold">Plt. Direktur', false);

        // keempat pemangku (termasuk Plt.) terhitung dalam baris "Direktur"
        $response->assertSee('4 pegawai');
    }

    public function test_generic_jabatan_melipat_plt_dan_variasi_direktur(): void
    {
        $fold = \App\Services\JabatanSyncService::class;

        $this->assertSame('Direktur', $fold::genericJabatan('Direktur Pengembangan Transmigrasi'));
        $this->assertSame('Direktur', $fold::genericJabatan('Direktur'));
        $this->assertSame('Direktur', $fold::genericJabatan('Plt. Direktur Pembangunan Kawasan'));
        $this->assertSame('Direktur', $fold::genericJabatan('PLH Direktur Pemetaan'));
        $this->assertSame('Direktur Jenderal', $fold::genericJabatan('Direktur Jenderal Pembangunan Kawasan'));
        $this->assertSame('Kepala Biro', $fold::genericJabatan('Plt. Kepala Biro Organisasi dan Sumber Daya Manusia'));
        $this->assertSame('Kepala Subbagian', $fold::genericJabatan('Kepala Subbagian Umum'));
    }

    /* ======== 2. FILTER UNIT KERJA ESELON II PADA PETA JABATAN ======== */

    public function test_peta_jabatan_menyediakan_filter_unit_eselon_ii(): void
    {
        $setjen = Unit::where('code', 'SETJEN')->first() ?? Unit::where('level', 'ES_I')->first();

        $biroA = Unit::firstOrCreate(
            ['code' => 'TES-BIRO-A'],
            ['name' => 'Biro Uji Eselon Dua A', 'level' => 'ES_II', 'parent_id' => $setjen?->id, 'sort_order' => 900],
        );
        $bagianA = Unit::firstOrCreate(
            ['code' => 'TES-BAG-A'],
            ['name' => 'Bagian Dalam Biro A', 'level' => 'ES_III', 'parent_id' => $biroA->id, 'sort_order' => 901],
        );
        $biroB = Unit::firstOrCreate(
            ['code' => 'TES-BIRO-B'],
            ['name' => 'Biro Uji Eselon Dua B', 'level' => 'ES_II', 'parent_id' => $setjen?->id, 'sort_order' => 902],
        );

        PositionMap::create([
            'name' => 'Analis Kepegawaian Ahli Muda',
            'existing_count' => 2,
            'required_count' => 3,
            'sort_order' => 1,
        ]);

        // pegawai fungsional: satu di bawah biro A (lewat bagiannya), satu di biro B
        $this->makeEmployee([
            'name' => 'Analis Biro A', 'unit_id' => $bagianA->id,
            'position_name' => 'Analis Kepegawaian', 'functional_level' => 'Ahli Muda', 'eselon' => null,
        ]);
        $this->makeEmployee([
            'name' => 'Analis Biro B', 'unit_id' => $biroB->id,
            'position_name' => 'Analis Kepegawaian', 'functional_level' => 'Ahli Muda', 'eselon' => null,
        ]);

        // dropdown filter tersedia
        $this->actingAs($this->admin)->get('/modul/peta-jabatan')
            ->assertOk()
            ->assertSee('name="es2"', false)
            ->assertSeeText('Unit Kerja (Eselon II)');

        // filter eselon II biro A: sebaran hanya memuat turunan biro A
        $filtered = $this->actingAs($this->admin)->get('/modul/peta-jabatan?es2='.$biroA->id);
        $filtered->assertOk();

        $unitNames = $filtered->viewData('table')->getCollection()->pluck('unit_name')->all();
        $this->assertContains('Bagian Dalam Biro A', $unitNames);
        $this->assertNotContains('Biro Uji Eselon Dua B', $unitNames);
    }

    /* ======== 3. PENGAJUAN LAYANAN ======== */

    public function test_pegawai_dapat_mengirim_pengajuan_layanan(): void
    {
        Storage::fake('public');

        $unit = Unit::where('code', 'OSDMRB')->first() ?? Unit::first();

        $response = $this->actingAs($this->pegawai)->post('/layanan', [
            'unit_id' => $unit->id,
            'service_type' => ServiceRequest::TYPE_KGB,
            'nota' => UploadedFile::fake()->create('nota-dinas.pdf', 120, 'application/pdf'),
            'supporting_link' => 'https://drive.google.com/file/bukti',
        ]);

        $response->assertRedirect(route('layanan.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('service_requests', [
            'employee_id' => $this->pegawaiEmployee->id,
            'unit_id' => $unit->id,
            'service_type' => ServiceRequest::TYPE_KGB,
            'status' => ServiceRequest::STATUS_PENDING,
        ]);

        $service = ServiceRequest::first();
        Storage::disk('public')->assertExists($service->nota_path);

        // pengajuan tampil pada daftar
        $this->actingAs($this->pegawai)->get('/layanan')
            ->assertOk()
            ->assertSeeText('Kenaikan Gaji Berkala')
            ->assertSeeText('Diajukan');
    }

    public function test_tata_letak_form_di_atas_daftar_pengajuan(): void
    {
        // revisi 21 Nov 2026: "Form Pengajuan Layanan" di ATAS, "Daftar Pengajuan" di BAWAH
        $html = $this->actingAs($this->pegawai)->get('/layanan')->assertOk()->getContent();

        $posForm = strpos($html, 'Form Pengajuan Layanan');
        $posDaftar = strpos($html, 'Daftar Pengajuan');

        $this->assertNotFalse($posForm);
        $this->assertNotFalse($posDaftar);
        $this->assertLessThan($posDaftar, $posForm, 'Form Pengajuan Layanan harus berada di atas Daftar Pengajuan.');
    }

    public function test_daftar_pengajuan_dibatasi_10_item_per_halaman(): void
    {
        $unit = Unit::first();

        ServiceRequest::factory()->count(14)->create([
            'unit_id' => $unit->id,
            'employee_id' => $this->pegawaiEmployee->id,
        ]);

        $response = $this->actingAs($this->pegawai)->get('/layanan');

        $response->assertOk();
        $this->assertCount(10, $response->viewData('requests')->items());
    }

    public function test_admin_dapat_memproses_dan_menolak_pengajuan(): void
    {
        $unit = Unit::first();

        $service = ServiceRequest::create([
            'unit_id' => $unit->id,
            'employee_id' => $this->pegawaiEmployee->id,
            'service_type' => ServiceRequest::TYPE_PENSIUN,
            'nota_path' => 'layanan/uji.pdf',
            'nota_name' => 'uji.pdf',
            'status' => ServiceRequest::STATUS_PENDING,
        ]);

        // proses
        $this->actingAs($this->admin)->post("/layanan/{$service->id}/proses")
            ->assertRedirect();
        $this->assertDatabaseHas('service_requests', ['id' => $service->id, 'status' => ServiceRequest::STATUS_PROCESSING]);

        // selesai
        $this->actingAs($this->admin)->post("/layanan/{$service->id}/selesai", ['note' => 'SK telah terbit'])
            ->assertRedirect();
        $this->assertDatabaseHas('service_requests', ['id' => $service->id, 'status' => ServiceRequest::STATUS_DONE]);
    }

    public function test_pegawai_lain_tidak_dapat_memproses_pengajuan(): void
    {
        $unit = Unit::first();

        $service = ServiceRequest::create([
            'unit_id' => $unit->id,
            'employee_id' => $this->pegawaiEmployee->id,
            'service_type' => ServiceRequest::TYPE_KP,
            'nota_path' => 'layanan/uji.pdf',
            'status' => ServiceRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->pegawai)->post("/layanan/{$service->id}/proses")->assertForbidden();
    }

    /* ======== 4. DATA RENANG PEGAWAI ======== */

    public function test_data_renang_menampilkan_statistik_rekap_dan_daftar(): void
    {
        $unitA = Unit::where('code', 'OSDMRB')->first() ?? Unit::first();
        $unitB = Unit::where('code', 'BIRO-ULP')->first() ?? Unit::skip(1)->first();

        $this->makeEmployee(['name' => 'Renang Lulus A', 'unit_id' => $unitA->id, 'swimming_skill' => 'lulus']);
        $this->makeEmployee(['name' => 'Renang Belum A', 'unit_id' => $unitA->id, 'swimming_skill' => 'belum']);
        $this->makeEmployee(['name' => 'Renang Lulus B', 'unit_id' => $unitB->id, 'swimming_skill' => 'lulus']);
        $this->makeEmployee(['name' => 'Renang Kosong B', 'unit_id' => $unitB->id, 'swimming_skill' => null]);

        // pegawai Non ASN TIDAK ikut dihitung
        $this->makeEmployee([
            'name' => 'Non ASN Renang', 'employee_type' => Employee::TYPE_NON_ASN, 'swimming_skill' => 'lulus',
        ]);

        $response = $this->actingAs($this->pegawai)->get('/data-renang');

        $response->assertOk()
            ->assertSeeText('Total Pegawai')
            ->assertSeeText('Lulus Berenang')
            ->assertSeeText('Belum Lulus')
            ->assertSeeText('Rekapitulasi Kemampuan Renang per Unit Kerja')
            ->assertSeeText('Daftar Data Renang Pegawai')
            ->assertDontSeeText('Non ASN Renang');

        $stats = $response->viewData('stats');

        // ekspektasi dihitung ulang dari basis data (seeder memuat pegawai lain)
        $asnActive = Employee::where('is_active', true)->where('employee_type', '!=', Employee::TYPE_NON_ASN);
        $this->assertSame((int) (clone $asnActive)->count(), $stats['total']);
        $this->assertSame((int) (clone $asnActive)->where('swimming_skill', 'lulus')->count(), $stats['lulus']);
        $this->assertSame((int) (clone $asnActive)->where('swimming_skill', 'belum')->count(), $stats['belum']);
        $this->assertSame((int) (clone $asnActive)->whereNull('swimming_skill')->count(), $stats['kosong']);

        // filter kemampuan + unit
        $filtered = $this->actingAs($this->pegawai)->get('/data-renang?unit='.$unitA->id.'&kemampuan=lulus');
        $filtered->assertOk()->assertSeeText('Renang Lulus A')->assertDontSeeText('Renang Lulus B');
    }

    /* ======== 5. SUSUNAN MENU SIDEBAR BARU ======== */

    public function test_sidebar_mengikuti_susunan_menu_baru(): void
    {
        $response = $this->actingAs($this->admin)->get('/data-renang');
        $content = $response->assertOk()->getContent();

        // "Modul Biro OSDMRB" diganti "Analisis Kepegawaian"
        $this->assertStringContainsString('Analisis Kepegawaian', $content);
        $this->assertStringNotContainsString('Modul Biro OSDMRB', $content);

        // urutan grup baru: Analisis Kepegawaian → Layanan Kepegawaian → Pendukung
        $this->assertGreaterThan(strpos($content, 'Peta Jabatan'), strpos($content, 'Data Jabatan Fungsional'));
        $this->assertGreaterThan(strpos($content, 'Data Jabatan Fungsional'), strpos($content, 'Data Jabatan Struktural'));
        $this->assertGreaterThan(strpos($content, 'Data Jabatan Struktural'), strpos($content, 'Data Jabatan Pelaksana'));
        $this->assertGreaterThan(strpos($content, 'Data Jabatan Pelaksana'), strpos($content, 'Data Renang Pegawai'));
        $this->assertGreaterThan(strpos($content, 'Data Renang Pegawai'), strpos($content, 'Pengajuan Layanan'));
        $this->assertGreaterThan(strpos($content, 'Pengajuan Layanan'), strpos($content, '">Pendukung</div>'));

        // menu baru tersedia
        $this->assertStringContainsString('Pengajuan Layanan', $content);
        $this->assertStringContainsString('Data Renang Pegawai', $content);
        $this->assertStringContainsString('Dokumen Kepegawaian', $content);
        $this->assertStringContainsString('Struktur Organisasi', $content);
    }
}
