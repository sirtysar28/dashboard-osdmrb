<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\PositionMap;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Smoke test Pembaruan 7 November 2026 — Point 4 (Peta Jabatan):
 *
 *  1. Halaman Peta Jabatan menampilkan SELURUH kolom kertas kerja:
 *     Jabatan dan Jenjang, Dukungan Lintas Program, Intervensi Program 5 T,
 *     Kualifikasi Pendidikan, Kompetensi, Kebutuhan, Pemangku Jabatan
 *     Eksisting, Kesenjangan Jumlah, Alternatif Penataan/Pemenuhan,
 *     Rekomendasi Utama.
 *  2. Import Excel kertas kerja (header otomatis dikenali — termasuk typo
 *     "eksisiting", header boleh tidak di baris 1) mengganti seluruh data.
 *  3. Pemangku Jabatan Eksisting & Kesenjangan Jumlah dihitung LANGSUNG dari
 *     data pegawai aktif; snapshot berkas hanya fallback bila jenis jabatan
 *     belum ada pemangkunya di data pegawai.
 *  4. Tombol import hanya untuk pengguna privileged (admin/biro SDM/super admin).
 */
class UpdateNovember7Test extends TestCase
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

        $this->pegawai = User::create([
            'name' => 'Pegawai Uji',
            'email' => 'pegawai-uji-nov7@osdmrb.go.id',
            'password' => Hash::make('password'),
            'employee_id' => null,
        ]);
        $this->pegawai->syncRoles(['pegawai']);
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'nip' => '19'.str_pad((string) random_int(1, 999999999), 9, '0'),
            'name' => 'Aaaa Pegawai Uji 7 Nov '.uniqid(),
            'employment_status_id' => EmploymentStatus::where('code', 'ASN')->value('id'),
            'unit_id' => Unit::where('code', 'OSDMRB')->value('id') ?? Unit::value('id'),
            'employee_type' => Employee::TYPE_ASN,
            'is_active' => true,
            'tmt_pns' => '2015-01-01',
        ], $attributes));
    }

    /**
     * Berkas Excel meniru struktur KERTAS KERJA EXCEL asli:
     * baris 1 kosong, header di baris 2 (termasuk typo "eksisiting"),
     * sel teks multi-baris, beberapa baris hanya berisi angka.
     */
    private function makeKertasKerjaFile(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $sheet->fromArray([
            [],
            ['No', 'Jabatan dan Jenjang', 'Dukungan Lintas Program', 'Intervensi Program 5 T',
                'Kualifikasi Pendidikan', 'Kompetensi', 'Kebutuhan', 'Pemangku jabatan eksisiting',
                'Kesenjangan Jumlah', 'Alternatif Penataan/Pemenuhan', 'Rekomendasi Utama'],
            [1, 'Analis Kepegawaian Ahli Muda',
                "Penataan organisasi\nPengelolaan SDM",
                'Trans Gotong Royong',
                ".- Berstatus PNS\n.- Berijazah paling rendah sarjana",
                'Mampu mengelola manajemen ASN',
                5, 2, -3,
                "1. uji kompetensi\n2. pengembangan kompetensi pegawai",
                "1. pengembangan kompetensi internal\n2. penerimaan mutasi"],
            [2, 'Penelaah Teknis Kebijakan', null, null, null, null, 4, 4, 0, null, null],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'kk-peta-jabatan').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /* ======== 1. SEMUA KOLOM KERTAS KERJA TAMPIL ======== */

    public function test_halaman_menampilkan_seluruh_kolom_kertas_kerja(): void
    {
        PositionMap::create([
            'name' => 'Analis Kepegawaian Ahli Muda',
            'cross_program' => 'Penataan organisasi',
            'program_intervention' => 'Trans Gotong Royong',
            'education_qualification' => 'Berstatus PNS',
            'competency' => 'Mampu mengelola manajemen ASN',
            'required_count' => 5,
            'existing_count' => 2,
            'gap_count' => -3,
            'fulfillment_alternative' => 'uji kompetensi',
            'main_recommendation' => 'pengembangan kompetensi internal',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan'))
            ->assertOk()
            ->getContent();

        foreach ([
            'Jabatan dan Jenjang',
            'Dukungan Lintas Program',
            'Intervensi Program 5 T',
            'Kualifikasi Pendidikan',
            'Kompetensi',
            'Kebutuhan',
            'Pemangku Jabatan Eksisting',
            'Kesenjangan Jumlah',
            'Alternatif Penataan/Pemenuhan',
            'Rekomendasi Utama',
        ] as $kolom) {
            $this->assertStringContainsString($kolom, $html, "Kolom kertas kerja \"{$kolom}\" harus tampil.");
        }
    }

    /* ======== 2. IMPORT KERTAS KERJA ======== */

    public function test_import_excel_mengisi_kertas_kerja_dan_mengganti_data_lama(): void
    {
        // data lama harus terganti setelah import
        PositionMap::create(['name' => 'Baris Lama', 'sort_order' => 1]);

        $file = $this->makeKertasKerjaFile();

        $this->actingAs($this->admin)
            ->post(route('modules.peta-jabatan.import'), [
                'file' => new \Illuminate\Http\UploadedFile($file, 'KERTAS KERJA EXCEL.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertRedirect(route('modules.peta-jabatan'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('position_maps', ['name' => 'Baris Lama']);
        $this->assertSame(2, PositionMap::count());

        $row = PositionMap::where('name', 'Analis Kepegawaian Ahli Muda')->first();
        $this->assertSame(5, $row->required_count);
        $this->assertSame(2, $row->existing_count);
        $this->assertSame('Trans Gotong Royong', $row->program_intervention);
        // sel multi-baris dibersihkan (awalan ".-" dirapikan, baris kosong dibuang)
        $this->assertSame("- Berstatus PNS\n- Berijazah paling rendah sarjana", $row->education_qualification);
        $this->assertSame("1. uji kompetensi\n2. pengembangan kompetensi pegawai", $row->fulfillment_alternative);

        // urutan baris mengikuti berkas
        $this->assertSame(1, PositionMap::where('name', 'Analis Kepegawaian Ahli Muda')->value('sort_order'));
        $this->assertSame(2, PositionMap::where('name', 'Penelaah Teknis Kebijakan')->value('sort_order'));
    }

    public function test_import_berkas_bukan_kertas_kerja_ditolak(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Nama', 'Umur'], ['A', 30]], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'kk-salah').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->actingAs($this->admin)
            ->post(route('modules.peta-jabatan.import'), [
                'file' => new \Illuminate\Http\UploadedFile($path, 'salah.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, PositionMap::count());
    }

    public function test_import_tidak_boleh_dilakukan_pegawai_biasa(): void
    {
        $file = $this->makeKertasKerjaFile();

        $this->actingAs($this->pegawai)
            ->post(route('modules.peta-jabatan.import'), [
                'file' => new \Illuminate\Http\UploadedFile($file, 'kk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertForbidden();

        $this->assertSame(0, PositionMap::count());
    }

    /* ======== 3. EKSISTING & KESENJANGAN DIHITUNG DARI DATA PEGAWAI ======== */

    public function test_eksisting_dan_kesenjangan_dihitung_dari_data_pegawai(): void
    {
        // 2 pemangku Analis Kepegawaian Ahli Muda + 1 Ahli Madya (jenjang lain)
        $this->makeEmployee(['position_name' => 'Analis Kepegawaian Ahli Muda', 'functional_level' => 'Ahli Muda', 'eselon' => null]);
        $this->makeEmployee(['position_name' => 'Analis Kepegawaian Ahli Muda', 'functional_level' => 'Ahli Muda', 'eselon' => null]);
        $this->makeEmployee(['position_name' => 'Analis Kepegawaian Ahli Madya', 'functional_level' => 'Ahli Madya', 'eselon' => null]);

        // kertas kerja: kebutuhan 5, snapshot eksisting 99 (harus diabaikan)
        PositionMap::create(['name' => 'Analis Kepegawaian Ahli Muda', 'required_count' => 5, 'existing_count' => 99, 'gap_count' => 94]);
        // jabatan TIDAK ada pemangkunya di data pegawai → fallback snapshot berkas
        PositionMap::create(['name' => 'Penelaah Teknis Kebijakan', 'required_count' => 4, 'existing_count' => 4, 'gap_count' => 0]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan'))
            ->assertOk()
            ->getContent();

        // baris live: eksisting 2, kesenjangan -3 (2 - 5)
        $this->assertStringContainsString('Analis Kepegawaian', $html);
        $this->assertStringContainsString('>-3<', $html, 'Kesenjangan 2 - 5 = -3 harus tampil sebagai badge.');
        $this->assertStringNotContainsString('>99<', $html, 'Snapshot eksisting berkas tidak boleh dipakai bila jabatan dikenali di data pegawai.');

        // baris fallback: eksisting 4 dari snapshot berkas
        $this->assertStringContainsString('Penelaah Teknis Kebijakan', $html);
        $this->assertStringContainsString('data kertas kerja', $html);
    }

    public function test_jabatan_dengan_pemangku_tapi_belum_dipetakan_tetap_tampil(): void
    {
        $this->makeEmployee(['position_name' => 'Arsiparis Ahli Pertama', 'functional_level' => 'Ahli Pertama', 'eselon' => null]);
        PositionMap::create(['name' => 'Analis Kepegawaian Ahli Muda', 'required_count' => 5]);

        $html = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Arsiparis', $html);
        $this->assertStringContainsString('Belum dipetakan di kertas kerja', $html);
    }

    /* ======== 4. TOMBOL IMPORT HANYA UNTUK PRIVILEGED ======== */

    public function test_tombol_import_hanya_untuk_pengguna_privileged(): void
    {
        PositionMap::create(['name' => 'Analis Kepegawaian Ahli Muda']);

        $adminHtml = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan'))->assertOk()->getContent();
        $this->assertStringContainsString('Import Excel', $adminHtml);
        $this->assertStringContainsString('Template', $adminHtml);

        $pegawaiHtml = $this->actingAs($this->pegawai)
            ->get(route('modules.peta-jabatan'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Import Excel', $pegawaiHtml);
    }

    /* ======== 5. EXPORT & TEMPLATE ======== */

    public function test_export_kertas_kerja_berhasil(): void
    {
        PositionMap::create(['name' => 'Analis Kepegawaian Ahli Muda', 'required_count' => 5, 'existing_count' => 2]);

        $response = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan.export'))
            ->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_template_format_sebelas_kolom_berhasil_diunduh(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('modules.peta-jabatan.template'))
            ->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }
}
