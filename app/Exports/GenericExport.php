<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel generik dari koleksi baris siap pakai (array of array).
 *
 * Catatan: lebar kolom di-set statis (bukan auto-size) supaya hemat memori
 * ketika mengekspor ribuan baris.
 */
class GenericExport implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    use Exportable;

    public function __construct(
        protected Collection $rows,
        protected array $headings,
        protected string $sheetTitle = 'Data',
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->headings as $index => $heading) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $widths[$letter] = min(42, max(14, mb_strlen((string) $heading) + 6));
        }

        return $widths;
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
}
