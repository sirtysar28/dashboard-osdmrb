<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export data pegawai ke Excel — mengikuti filter aktif di halaman daftar pegawai.
 *
 * Lebar kolom statis (tanpa auto-size) agar hemat memori untuk ribuan baris.
 */
class EmployeesExport implements FromQuery, WithMapping, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    use Exportable;

    public function __construct(protected $query) {}

    public function query()
    {
        return $this->query->with(['unit', 'education', 'rank', 'employmentStatus']);
    }

    public function title(): string
    {
        return 'Data Pegawai';
    }

    public function headings(): array
    {
        return [
            'No',
            'NIP',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Email',
            'Telepon',
            'Status Kepegawaian',
            'Golongan',
            'Pangkat',
            'Pendidikan Terakhir',
            'Unit Kerja',
            'Jabatan',
            'Eselon',
            'Level Fungsional',
            'TMT Jabatan',
            'TMT Golongan',
            'TMT CPNS',
            'TMT ASN',
            'Batas Pensiun',
            'NPWP',
            'No. Karpeg',
            'Status Aktif',
        ];
    }

    /**
     * @param  Employee  $employee
     */
    public function map($employee): array
    {
        return [
            $employee->id,
            $employee->nip,
            $employee->name,
            $employee->gender_label,
            $employee->birth_place,
            $employee->birth_date?->format('d/m/Y'),
            $employee->religion,
            $employee->email,
            $employee->phone,
            $employee->employmentStatus?->name,
            $employee->rank?->code,
            $employee->rank?->name,
            $employee->education?->name,
            $employee->unit?->name,
            $employee->position_name,
            $employee->eselon,
            $employee->functional_level,
            $employee->tmt_jabatan?->format('d/m/Y'),
            $employee->tmt_golongan?->format('d/m/Y'),
            $employee->tmt_cpns?->format('d/m/Y'),
            $employee->tmt_pns?->format('d/m/Y'),
            $employee->retirement_date?->format('d/m/Y'),
            $employee->npwp,
            $employee->karpeg,
            $employee->is_active ? 'Aktif' : 'Non-aktif',
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
        // 25 kolom (A..Y) — cukup lega tanpa perhitungan auto-size
        $widths = [];

        foreach (range(1, 25) as $index) {
            $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index)] = 18;
        }

        // kolom nama & jabatan lebih lebar
        $widths['C'] = 28; // nama
        $widths['O'] = 30; // jabatan
        $widths['A'] = 6;  // no
        $widths['B'] = 26; // nip

        return $widths;
    }
}
