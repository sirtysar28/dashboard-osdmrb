<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template import massal pegawai.
 *
 * Format kolom SAMA PERSIS dengan berkas "Data Dashboard.xlsx" yang selama ini
 * dipakai instansi — sehingga berkas lama bisa langsung diunggah tanpa perlu
 * mengunduh & memindahkan data ke template khusus.
 */
class EmployeesTemplateExport implements FromArray, WithTitle, WithColumnWidths, WithStyles
{
    use Exportable;

    public function array(): array
    {
        return [
            [
                1, 'Budi Santoso', '198504122010001001', 'PNS', 'Analis Kepegawaian Ahli Madya', 'Fungsional',
                'Biro Organisasi, Sumber Daya Manusia dan Reformasi Birokrasi', 'Sekretariat Jenderal Kementerian Transmigrasi', '-',
                '', 'Madya', '01/09/2024', 'Pembina Utama Muda', '(IV.c)', 'IV', '01/04/2024', '01 April 2028',
                '01/10/2010', '15 tahun 6 bulan', '01/10/2011', '', '', '',
                'S2 Ilmu Administrasi Universitas Indonesia', 'S1 Ilmu Komunikasi Universitas Indonesia', '',
                'S2', '', '', '', '', '',
                'Jakarta', '12 April 1985', '41', '1/9/2043', 'Laki-laki', 'Islam',
                'Jl. Merdeka No. 1 Jakarta', 'budi@instansi.go.id', '081234567890', '3174011204850001',
            ],
            [
                2, 'Siti Aminah', '199008202015032002', 'PPPK', 'Pengelola Keuangan Ahli Pertama', 'Fungsional',
                'Bagian Sumber Daya Manusia dan Diklat', 'Sekretariat Jenderal Kementerian Transmigrasi', '-',
                '', 'Ahli Pertama', '01/03/2025', 'Penata', '(III.c)', 'III', '01/01/2025', '01 Januari 2029',
                '01/03/2015', '11 tahun', '01/03/2016', '', '', '',
                'S1 Akuntansi Universitas Padjadjaran', '', '', 'S1', '', '', '', '', '',
                'Bandung', '20 Agustus 1990', '35', '31/8/2048', 'Perempuan', 'Islam',
                'Jl. Asia Afrika No. 8 Bandung', 'siti@instansi.go.id', '089876543210', '3273012008900002',
            ],
        ];
    }

    public function title(): string
    {
        return 'DATA PEG';
    }

    /**
     * Header identik dengan berkas "Data Dashboard.xlsx" instansi.
     */
    public function headings(): array
    {
        return [
            'NO',
            'NAMA',
            'NIP',
            'STATUS',
            'Nama Jabatan',
            'Level',
            'Es. II',
            'Es. I',
            'Es. III',
            'Level Eselon',
            'Level Fungsional',
            'TMT JABATAN',
            'PANGKAT',
            'GOLONGAN',
            'GOL',
            'TMT GOL',
            'KENAIKAN PANGKAT',
            'TMT CPNS',
            'MASA KERJA',
            'TMT PNS',
            'TMT KGB',
            'Masa Kerja Saat KGB',
            'KGB BERIKUTNYA',
            'PENDIDIKAN TERAKHIR 1',
            'PENDIDIKAN TERAKHIR 2',
            'PENDIDIKAN TERAKHIR 3',
            'LEVEL PENDIDIKAN',
            'SLTA',
            'D3',
            'S1',
            'S2',
            'S3',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'Usia',
            'BATAS USIA PENSIUN',
            'JENIS KELAMIN',
            'AGAMA',
            'ALAMAT',
            'EMAIL',
            'NO TLP',
            'NO KTP',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '43538F']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach (range(1, count($this->headings())) as $index) {
            $widths[Coordinate::stringFromColumnIndex($index)] = 20;
        }

        $widths['B'] = 26; // nama
        $widths['C'] = 24; // nip
        $widths['E'] = 32; // jabatan
        $widths['G'] = 34; // es ii
        $widths['H'] = 34; // es i
        $widths['X'] = 36; // pendidikan 1

        return $widths;
    }
}
