<?php

namespace App\Services;

use App\Models\ArchiveCategory;
use App\Models\Campus;
use App\Models\EducationLevel;
use App\Models\EmploymentStatus;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\PositionType;
use App\Models\Rank;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export seluruh master data ke Excel (satu sheet per tabel) atau PDF.
 */
class MasterDataExporter
{
    public static function handle(string $format)
    {
        self::raiseMemoryLimit();

        if (strtolower($format) === 'pdf') {
            $sections = array_map(function (array $section) {
                $section['rows'] = $section['query']->get()->map($section['map']);

                return $section;
            }, self::sections());

            return Pdf::loadView('exports.master', ['sections' => $sections])
                ->setPaper('a4', 'landscape')
                ->download('master-data-'.now()->format('Ymd-His').'.pdf');
        }

        return Excel::download(new class(self::sections()) implements WithMultipleSheets
        {
            public function __construct(protected array $sections) {}

            public function sheets(): array
            {
                return array_map(
                    fn (array $section) => new SimpleSheet(
                        $section['query'],
                        $section['columns'],
                        $section['map'],
                        $section['title'],
                    ),
                    $this->sections
                );
            }
        }, 'master-data-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * Naikkan batas memori PHP untuk ekspor berukuran besar (bila diizinkan server).
     */
    public static function raiseMemoryLimit(string $limit = '512M'): void
    {
        $current = ini_get('memory_limit');

        if ($current !== '-1' && (int) $current < (int) $limit) {
            @ini_set('memory_limit', $limit);
        }
    }

    public static function sections(): array
    {
        return [
            [
                'title' => 'Unit Kerja',
                'query' => Unit::query()->with('parent')->orderBy('level')->orderBy('name'),
                'columns' => ['Kode', 'Nama', 'Level', 'Induk', 'Alamat'],
                'map' => fn (Unit $row) => [
                    $row->code,
                    $row->name,
                    $row->level_label,
                    $row->parent?->name ?? '-',
                    $row->address,
                ],
            ],
            [
                'title' => 'Pendidikan',
                'query' => EducationLevel::query()->orderBy('sort_order'),
                'columns' => ['Kode', 'Nama', 'Urutan'],
                'map' => fn (EducationLevel $row) => [$row->code, $row->name, $row->sort_order],
            ],
            [
                'title' => 'Kampus',
                'query' => Campus::query()->orderBy('sort_order')->orderBy('name'),
                'columns' => ['Nama Kampus', 'Kota', 'Jenis', 'Urutan'],
                'map' => fn (Campus $row) => [$row->name, $row->city, $row->type_label, $row->sort_order],
            ],
            [
                'title' => 'Golongan',
                'query' => Rank::query()->orderBy('sort_order'),
                'columns' => ['Kode', 'Nama Pangkat', 'Golongan', 'Jenis', 'Urutan'],
                'map' => fn (Rank $row) => [
                    $row->code,
                    $row->name,
                    $row->group_name,
                    $row->is_pppk ? 'PPPK' : 'PNS',
                    $row->sort_order,
                ],
            ],
            [
                'title' => 'Status ASN',
                'query' => EmploymentStatus::query()->orderBy('name'),
                'columns' => ['Kode', 'Nama'],
                'map' => fn (EmploymentStatus $row) => [$row->code, $row->name],
            ],
            [
                'title' => 'Level Jabatan',
                'query' => JobLevel::query()->orderBy('sort_order'),
                'columns' => ['Kode', 'Nama', 'Urutan'],
                'map' => fn (JobLevel $row) => [$row->code, $row->name, $row->sort_order],
            ],
            [
                'title' => 'Jenis Jabatan',
                'query' => PositionType::query()->orderBy('name'),
                'columns' => ['Kode', 'Nama'],
                'map' => fn (PositionType $row) => [$row->code, $row->name],
            ],
            [
                'title' => 'Jabatan',
                'query' => Position::query()->with(['positionType', 'jobLevel'])->orderBy('name'),
                'columns' => ['Kode', 'Nama Jabatan', 'Jenis', 'Level', 'Deskripsi'],
                'map' => fn (Position $row) => [
                    $row->code,
                    $row->name,
                    $row->positionType?->name,
                    $row->jobLevel?->name,
                    $row->description,
                ],
            ],
            [
                'title' => 'Klasifikasi Arsip',
                'query' => ArchiveCategory::query()->withCount('archives')->orderBy('code'),
                'columns' => ['Kode', 'Nama', 'Jumlah Arsip', 'Deskripsi'],
                'map' => fn (ArchiveCategory $row) => [
                    $row->code,
                    $row->name,
                    $row->archives_count,
                    $row->description,
                ],
            ],
        ];
    }
}

/**
 * Sheet sederhana dari query + mapper.
 */
class SimpleSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    use Exportable;

    public function __construct(
        protected $query,
        protected array $columns,
        protected $map,
        protected string $title,
    ) {}

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function map($row): array
    {
        return ($this->map)($row);
    }

    public function title(): string
    {
        return preg_replace('/[\[\]:*?\/\\\\]/u', '', substr($this->title, 0, 28));
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach ($this->columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $widths[$letter] = min(42, max(14, mb_strlen((string) $column) + 6));
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
