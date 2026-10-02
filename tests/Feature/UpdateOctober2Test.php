<?php

namespace Tests\Feature;

use App\Imports\NonAsnEmployeesImport;
use App\Models\Employee;
use App\Models\Unit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Smoke test pembaruan 2 Oktober 2026 — import Non ASN format bank data baru:
 *
 *  NO. | NAMA LENGKAP | NIK | NO. HP | EMAIL | AGAMA | TEMPAT | TANGGAL LAHIR |
 *  ALAMAT DOMISILI | UNIT PENEMPATAN
 *
 *  1. Data lengkap terbaca: NIK (termasuk apostrof & sel numeric), email
 *     berspasi, tanggal lahir campuran (sel tanggal, serial, dd/mm/yyyy,
 *     "6 Januari 2000", typo "Deseember").
 *  2. Unit penempatan dipetakan ke unit kerja dashboard (segment sebelum
 *     koma, tahan typo/potongan nama); tak dikenal → Sekretariat Jenderal.
 *  3. Berkas multi-sheet (Pramubakti / Driver / Teknisi) diproses sekaligus,
 *     kategori per judul sheet.
 *  4. Import ulang memperbarui (match NIK / nama+kategori), bukan duplikat.
 *  5. Format lama (NO | NAMA, NO | NAMA | JABATAN) tetap terbaca.
 */
class UpdateOctober2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function writeSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $path = storage_path('app/test-non-asn-import-oct2-'.uniqid().'.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** Header format bank data baru. */
    private function newFormatHeader(): array
    {
        return ['No.', 'Nama Lengkap', 'NIK', 'No. HP', 'Email', 'Agama', 'Tempat',
            'Tanggal Lahir', 'Alamat Domisili', 'Unit Penempatan'];
    }

    public function test_import_format_baru_membaca_data_lengkap(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');

        $rows = [
            $this->newFormatHeader(),
            // NIK apostrof, email berspasi, sel tanggal Excel, unit biro lengkap
            [1, 'A. Hendra Mulyadi', "'3275020901820012", '085974780995',
                ' pol t ak s i m a nj unt a k 28 @ gm a i l . c om ', 'Islam', 'Garut',
                \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime('1982-01-09')),
                'jl. Patriot Dalam II RT.01 RW.01 Bekasi', 'Biro Keuangan dan Barang Milik Negara, Sekretariat Jenderal'],
            // NIK numeric (float saat dibaca), tanggal serial, unit dipotong namanya
            [2, 'Deden Riadi (Ridwan Syafii)', 3175020811780005, '088293570957',
                'DedenRiadi72@gmail.com', 'Islam', 'Jakarta', 26040,
                'Jl Pisangan Lama III', 'Pusat Data dan Informasi'],
            // tanggal teks dd/mm/yyyy, unit typo "Transmigarsi"
            [3, 'Henry Siahaan', '3175051509840007', '081293778015',
                'henrysiahaan757@yahoo.com', 'Kristen', 'Jakarta', '15/09/1984',
                'Jl Lapan', 'Sekretariat Direktorat Jenderal Pembangunan dan Pengembangan Kawasan Transmigarsi'],
            // tanggal "21-02-1995", unit tak dikenal → Setjen
            [4, 'Irmawati', '3271066102950003', '089509748770',
                'tyta020516@gmail.com', 'Islam', 'Bogor', '21-02-1995',
                'kp.sawah rt 01/06', 'Cleaning Servis'],
            // bulan Indonesia + typo, staf ahli dipotong koma
            [5, 'Indra Kesuma', '3271062803710008', '087870010027',
                'kindra0371@gmail.com', 'Islam', 'Tanjung Karang', '28 Maret 1971',
                'Taman Cimanggu', 'Staf Ahli Bidang Pembangunan, Kemasyarakatan dan Lingkungan Hidup, Sekretariat Jenderal'],
            [6, 'Danu Dwi Pangestu', "'3175010112971002", '081380643330',
                'danu.dwi1297@gmail.com', 'Islam', 'Jakarta', '01 Deseember 1997',
                'Jl. Kramat Pulu Dalam II', 'Biro Umum dan Layanan Pengadaan, Sekretariat Jenderal'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        // pastikan sel NIK numeric tetap numeric & tanggal baris 2 sel tanggal
        $sheet->getStyle('C3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
        $sheet->getStyle('H2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');

        $path = $this->writeSpreadsheet($spreadsheet);
        $result = (new NonAsnEmployeesImport)->import($path, 'BANK DATA CLEANING SERVIS.xlsx');
        unlink($path);

        // kategori terdeteksi dari nama berkas
        $this->assertSame('Cleaning Service', $result['category']);
        $this->assertSame(6, $result['created']);

        $hendra = Employee::where('name', 'A. Hendra Mulyadi')->first();
        $this->assertNotNull($hendra);
        $this->assertSame('3275020901820012', $hendra->nip);          // apostrof dibuang
        $this->assertSame('085974780995', $hendra->phone);
        $this->assertSame('poltaksimanjuntak28@gmail.com', $hendra->email); // spasi dibersihkan
        $this->assertSame('Islam', $hendra->religion);
        $this->assertSame('Garut', $hendra->birth_place);
        $this->assertSame('1982-01-09', $hendra->birth_date->format('Y-m-d'));
        $this->assertSame(Unit::where('code', 'BIRO-KBMN')->value('id'), $hendra->unit_id);

        // NIK numeric float → tanpa notasi ilmiah; serial 26040 → 1971-04-15;
        // "Pusat Data dan Informasi" → PUS-DATIN lewat pencocokan awalan nama
        $deden = Employee::where('name', 'Deden Riadi (Ridwan Syafii)')->first();
        $this->assertNotNull($deden);
        $this->assertSame('3175020811780005', $deden->nip);
        $this->assertSame('1971-04-17', $deden->birth_date->format('Y-m-d'));
        $this->assertSame(Unit::where('code', 'PUS-DATIN')->value('id'), $deden->unit_id);

        // tanggal teks + unit typo dipetakan lewat kemiripan
        $henry = Employee::where('name', 'Henry Siahaan')->first();
        $this->assertSame('1984-09-15', $henry->birth_date->format('Y-m-d'));
        $this->assertSame(Unit::where('code', 'SET-DJKWSN')->value('id'), $henry->unit_id);

        // unit tak dikenal → Sekretariat Jenderal (bawaan)
        $irmawati = Employee::where('name', 'Irmawati')->first();
        $this->assertSame(Unit::where('code', 'SETJEN')->value('id'), $irmawati->unit_id);

        // staf ahli: segment pertama koma dipotong → cocok via awalan nama unit
        $indra = Employee::where('name', 'Indra Kesuma')->first();
        $this->assertSame('1971-03-28', $indra->birth_date->format('Y-m-d'));
        $this->assertSame(Unit::where('code', 'STAF-AH-PKLH')->value('id'), $indra->unit_id);

        // typo "Deseember" tetap terbaca sebagai Desember
        $danu = Employee::where('name', 'Danu Dwi Pangestu')->first();
        $this->assertSame('1997-12-01', $danu->birth_date->format('Y-m-d'));
    }

    public function test_import_ulang_memperbarui_bukan_duplikat(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            $this->newFormatHeader(),
            [1, 'Rini', '3175044911770002', '083890144049', 'rinisahid5@gmail.com',
                'Islam', 'Jakarta', '09/11/1977', 'Jakarta Timur', 'Biro Hukum, Sekretariat Jenderal'],
        ], null, 'A1');

        $path = $this->writeSpreadsheet($spreadsheet);
        $first = (new NonAsnEmployeesImport)->import($path, 'cleaning.xlsx');
        $this->assertSame(1, $first['created']);

        // baris kedua: nomor HP berubah → harus update, bukan duplikat
        $spreadsheet->getActiveSheet()->fromArray([
            [1, 'Rini', '3175044911770002', '081200000000', 'rinisahid5@gmail.com',
                'Islam', 'Jakarta', '09/11/1977', 'Jakarta Timur', 'Biro Hukum, Sekretariat Jenderal'],
        ], null, 'A2');

        $path2 = $this->writeSpreadsheet($spreadsheet);
        $result = (new NonAsnEmployeesImport('Cleaning Service'))->import($path2, 'cleaning.xlsx');
        unlink($path);
        unlink($path2);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Employee::where('name', 'Rini')->count());
        $this->assertSame('081200000000', Employee::where('name', 'Rini')->value('phone'));
        $this->assertSame(Unit::where('code', 'BIRO-HUKUM')->value('id'), Employee::where('name', 'Rini')->value('unit_id'));
    }

    public function test_import_multi_sheet_kategori_mengikuti_judul_sheet(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pramubakti');
        $sheet->fromArray([
            $this->newFormatHeader(),
            [1, 'Arfiyanto Bismantoro', '3275100601000011', '08997985884',
                'arfiyantobisma@gmail.com', 'Islam', 'Bekasi', '6 Januari 2000',
                'Jl. Mandar No.60', 'Biro Keuangan dan Barang Milik Negara, Sekretariat Jenderal'],
        ], null, 'A1');

        $driver = $spreadsheet->createSheet();
        $driver->setTitle('Driver');
        $driver->fromArray([
            $this->newFormatHeader(),
            [1, 'Indra Kesuma Driver', '3271062803710009', '087870010027',
                'kindra0372@gmail.com', 'Islam', 'Tanjung Karang', '28 Maret 1971',
                'Taman Cimanggu', 'Biro Perencanaan, Kerja Sama, dan Hubungan Masyarakat, Sekretariat Jenderal'],
        ], null, 'A1');

        $teknisi = $spreadsheet->createSheet();
        $teknisi->setTitle('Teknisi');
        $teknisi->fromArray([
            $this->newFormatHeader(),
            [1, 'Valent Andrean', "'1802150904040001", '085934580759',
                'valentandrean@gmail.com', 'Islam', 'Sumberjaya', '9 April 2004',
                'Jl. Penggalang 1 No. 22', 'Biro Umum dan Layanan Pengadaan, Sekretariat Jenderal'],
        ], null, 'A1');

        $path = $this->writeSpreadsheet($spreadsheet);
        $result = (new NonAsnEmployeesImport)->import($path, 'Data Pramubakti, Driver dan Teknisi.xlsx');
        unlink($path);

        $this->assertSame(['Pramubakti', 'Driver', 'Teknisi'], $result['categories']);
        $this->assertSame('Pramubakti, Driver, Teknisi', $result['category']);
        $this->assertSame(3, $result['created']);

        $this->assertSame('Pramubakti', Employee::where('name', 'Arfiyanto Bismantoro')->value('category'));
        $this->assertSame('Driver', Employee::where('name', 'Indra Kesuma Driver')->value('category'));
        $this->assertSame('Teknisi', Employee::where('name', 'Valent Andrean')->value('category'));
        $this->assertSame('Personil Driver', Employee::where('name', 'Indra Kesuma Driver')->value('position_name'));
        $this->assertSame(Unit::where('code', 'BIRO-PKHM')->value('id'), Employee::where('name', 'Indra Kesuma Driver')->value('unit_id'));
    }

    public function test_format_lama_daftar_nama_sederhana_tetap_terbaca(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['DAFTAR PERSONIL SECURITY'], [], ['NO', 'NAMA'], [1, 'Ujang Lama'], [2, 'Encep Lama']], null, 'A1');

        $path = $this->writeSpreadsheet($spreadsheet);
        $result = (new NonAsnEmployeesImport('Security'))->import($path, 'security-lama.xlsx');
        unlink($path);

        $this->assertSame(2, $result['created']);

        $ujang = Employee::where('name', 'Ujang Lama')->first();
        $this->assertNotNull($ujang);
        $this->assertSame('Security', $ujang->category);
        $this->assertSame('Personil Security', $ujang->position_name);
        $this->assertSame(Unit::where('code', 'SETJEN')->value('id'), $ujang->unit_id);
        $this->assertNull($ujang->email); // format lama tanpa data tambahan
    }

    public function test_berkas_tanpa_kolom_nama_ditolak(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['LAPORAN'], ['bulanan']], null, 'A1');

        $path = $this->writeSpreadsheet($spreadsheet);

        try {
            (new NonAsnEmployeesImport)->import($path, 'bukan-daftar.xlsx');
            $this->fail('Berkas tanpa kolom NAMA seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('NAMA', $e->getMessage());
        } finally {
            unlink($path);
        }
    }
}
