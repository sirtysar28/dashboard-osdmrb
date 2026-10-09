<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Smoke test Catatan Masukan 7 Oktober 2026:
 *
 *  1. Urutan pilihan unit kerja eselon I, II, III mengikuti daftar struktur
 *     kementerian (termasuk dropdown Eselon I dashboard & form pegawai).
 *  2. Filter seperti Pendidikan S3 tidak lagi tergabung dengan data Non ASN
 *     (jumlah Non ASN otomatis 0 saat filter ASN-only dipakai).
 *  3. CV pegawai: Riwayat Pendidikan dimulai dari Pendidikan 1 dan jenjang
 *     dituliskan eksplisit (Diploma/Sarjana/Magister/Doktor).
 *  4. Analis jabatan fungsional: kolom pemangku diganti JUMLAH pegawai yang
 *     bisa diklik untuk memunculkan daftar pegawai pemangku jabatan.
 *  5. Analis jabatan fungsional & struktural: formasi kosong tidak dimunculkan.
 *  6. Jabatan struktural "Sekretaris Direktur Jenderal" (salah nama) dihapus.
 */
class UpdateOctober7Test extends TestCase
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
            'name' => 'Aaaa Pegawai Uji 7 Okt '.uniqid(),
            'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
            'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
            'employee_type' => Employee::TYPE_ASN,
            'is_active' => true,
            'tmt_pns' => '2015-01-01',
        ], $attributes));
    }

    /* ======== 1. URUTAN PILIHAN UNIT KERJA ======== */

    public function test_dropdown_eselon_i_dashboard_mengikuti_urutan_struktur(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        // opsi Eselon I pada filter dashboard urut: Setjen → Ditjen Ekbang →
        // Ditjen Kawasan → Itjen (sebelumnya masih urut nama)
        $posSetjen = mb_strpos($html, 'Sekretariat Jenderal');
        $posEkbang = mb_strpos($html, 'Direktorat Jenderal Pengembangan Ekonomi');
        $posKawasan = mb_strpos($html, 'Direktorat Jenderal Pembangunan dan Pengembangan Kawasan');
        $posItjen = mb_strpos($html, 'Inspektorat Jenderal');

        $this->assertNotFalse($posSetjen);
        $this->assertNotFalse($posEkbang);
        $this->assertNotFalse($posKawasan);
        $this->assertNotFalse($posItjen);
        $this->assertLessThan($posEkbang, $posSetjen);
        $this->assertLessThan($posKawasan, $posEkbang);
        $this->assertLessThan($posItjen, $posKawasan);
    }

    public function test_dropdown_unit_kerja_form_pegawai_mengikuti_urutan_struktur(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();

        // Balai tidak lagi mendahului unit eselon I (sebelumnya urut abjad level)
        $posSetjen = mb_strpos($html, 'Sekretariat Jenderal</option>');
        $posBalai = mb_strpos($html, 'Balai Besar Pelatihan dan Pemberdayaan Masyarakat Transmigrasi Yogyakarta</option>');

        $this->assertNotFalse($posSetjen);
        $this->assertNotFalse($posBalai);
        $this->assertLessThan($posBalai, $posSetjen);
    }

    /* ======== 2. FILTER PENDIDIKAN TIDAK TERGABUNG NON ASN ======== */

    public function test_filter_pendidikan_tidak_mengikutsertakan_non_asn(): void
    {
        $s3 = \App\Models\EducationLevel::where('code', 'S3')->firstOrFail();

        $asnS3 = $this->makeEmployee(['education_level_id' => $s3->id]);
        $this->makeEmployee(['employee_type' => Employee::TYPE_NON_ASN, 'category' => 'Security']);

        $service = app(DashboardService::class);

        // tanpa filter → Non ASN terhitung
        $this->assertSame(
            1,
            (clone $service->nonAsnQuery([]))->count(),
            'Tanpa filter, Non ASN tetap terhitung.'
        );

        // filter pendidikan S3 → Non ASN = 0 (tidak tergabung)
        $this->assertSame(
            0,
            (clone $service->nonAsnQuery(['education' => [$s3->id]]))->count(),
            'Filter pendidikan tidak boleh mengikutsertakan Non ASN.'
        );

        // KPI dashboard: Total Keseluruhan hanya berisi ASN S3
        $filters = ['education' => [$s3->id]];
        $query = $service->applyFilters($service->baseQuery(), $filters);
        $summary = $service->getSummary($query, $service->nonAsnQuery($filters));

        $this->assertSame(1, $summary['totalAsn']);
        $this->assertSame(0, $summary['nonAsnCount']);
        $this->assertSame(1, $summary['totalAll']);
        $this->assertSame($asnS3->id, $summary ? Employee::where('education_level_id', $s3->id)->value('id') : null);
    }

    /* ======== 3. CV — MULAI DARI PENDIDIKAN 1 + JENJANG ======== */

    public function test_cv_riwayat_pendidikan_dimulai_dari_pendidikan_1_dengan_jenjang(): void
    {
        $employee = $this->makeEmployee([
            'education_1' => 'Teknik Informatika, Universitas Indonesia',
            'education_2' => 'Magister Administrasi, Universitas Gadjah Mada',
            'education_3' => 'Doktor Manajemen, Universitas Padjadjaran',
        ]);

        $html = view('employees.cv', ['employee' => $employee->loadMissing('rank', 'employmentStatus', 'unit')])->render();

        // jenjang dituliskan eksplisit
        $this->assertStringContainsString('Pendidikan 1 — Diploma / Sarjana (S1)', $html);
        $this->assertStringContainsString('Pendidikan 2 — Magister (S2)', $html);
        $this->assertStringContainsString('Pendidikan 3 — Doktor (S3)', $html);

        // urutan mulai dari Pendidikan 1 (education_1) lalu naik ke S2/S3
        $posP1 = mb_strpos($html, 'Pendidikan 1 — Diploma / Sarjana (S1)');
        $posP2 = mb_strpos($html, 'Pendidikan 2 — Magister (S2)');
        $posP3 = mb_strpos($html, 'Pendidikan 3 — Doktor (S3)');
        $this->assertLessThan($posP2, $posP1);
        $this->assertLessThan($posP3, $posP2);
    }

    /* ======== 4. FUNGSIONAL — JUMLAH PEGAWAI BISA DIKLIK ======== */

    public function test_analis_jabatan_fungsional_jumlah_pegawai_bisa_diklik(): void
    {
        $pegawai = $this->makeEmployee([
            'name' => 'Zaaa Pemangku Fungsional',
            'position_name' => 'Analis Kepegawaian Ahli Muda',
            'functional_level' => 'Ahli Muda',
            'eselon' => null,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan'))
            ->assertOk()
            ->getContent();

        // kolom diganti "Jumlah Pegawai" dan tombolnya membuka popup daftar pegawai
        $this->assertStringContainsString('Jumlah Pegawai', $html);
        $this->assertStringContainsString('data-bs-target="#daftarPegawaiModal"', $html);
        $this->assertStringContainsString('1 pegawai', $html);

        // data pegawai pemangku dikirim ke popup (nama + unit)
        $this->assertStringContainsString(e($pegawai->name), $html);
        $this->assertStringContainsString('Biro Organisasi', $html);
    }

    /* ======== 5. FORMASI KOSONG TIDAK DIMUNCULKAN ======== */

    public function test_analisis_jabatan_struktural_formasi_kosong_tidak_tampil(): void
    {
        // satu-satunya pejabat: Kepala Biro (eselon II)
        $this->makeEmployee([
            'name' => 'Zaaa Kepala Biro Uji',
            'position_name' => 'Kepala Biro Organisasi, Sumber Daya Manusia, dan Reformasi Birokrasi',
            'eselon' => 'II',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan-struktural'))
            ->assertOk()
            ->getContent();

        // jabatan yang ada pemangkunya tampil (demo: Kabiro, Kabag, Kasubag)
        $this->assertStringContainsString('<td class="fw-semibold">Kepala Biro</td>', $html);
        $this->assertStringContainsString('<td class="fw-semibold">Kepala Subbagian</td>', $html);

        // formasi kosong tidak dimunculkan sebagai BARIS tabel — mis. jabatan
        // umum yang tidak punya pemangku sama sekali pada data demo
        $this->assertStringNotContainsString('<td class="fw-semibold">Kepala Balai Besar</td>', $html);
        $this->assertStringNotContainsString('<td class="fw-semibold">Kepala Balai</td>', $html);
        $this->assertStringNotContainsString('<td class="fw-semibold">Sekretaris Jenderal</td>', $html);
        $this->assertStringNotContainsString('<td class="fw-semibold">Kepala Pusat</td>', $html);
        $this->assertStringNotContainsString('<td class="fw-semibold">Direktur</td>', $html);
    }

    public function test_analisis_jabatan_fungsional_formasi_kosong_tidak_tampil(): void
    {
        $this->makeEmployee([
            'name' => 'Zaaa Auditor Uji',
            'position_name' => 'Auditor Ahli Muda',
            'functional_level' => 'Ahli Muda',
            'eselon' => null,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Auditor Ahli Muda', $html);
        $this->assertStringNotContainsString('Arsiparis Ahli Pertama', $html);
    }

    /* ======== 6. HAPUS "SEKRETARIS DIREKTUR JENDERAL" ======== */

    public function test_jabatan_sekretaris_direktur_jenderal_dihapus(): void
    {
        // migrasi pembersih sudah dijalankan RefreshDatabase
        $this->assertDatabaseMissing('positions', ['code' => 'STR-SETDITJEN']);
        $this->assertDatabaseMissing('positions', ['name' => 'Sekretaris Direktur Jenderal']);

        // nama resmi tetap dikenali dari data pegawai
        $this->makeEmployee([
            'name' => 'Zaaa Sekretaris Ditjen',
            'position_name' => 'Sekretaris Direktorat Jenderal Pengembangan Ekonomi dan Pemberdayaan Masyarakat Transmigrasi',
            'eselon' => 'II',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.analisis-jabatan-struktural'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sekretaris Direktorat Jenderal', $html);
        $this->assertStringNotContainsString('>Sekretaris Direktur Jenderal<', $html);
    }
}
