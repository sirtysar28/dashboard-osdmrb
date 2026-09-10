<?php

namespace App\Traits;

use App\Exports\GenericExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Helper export tabel data ke Excel (.xlsx) dan PDF.
 */
trait ExportsTable
{
    /**
     * @param  string  $format  'xlsx' | 'pdf'
     * @param  array  $columns  Judul kolom
     * @param  iterable  $rows  Baris data (array of array / Collection)
     */
    protected function exportTable(
        string $format,
        string $title,
        array $columns,
        iterable $rows,
        string $filename,
        ?string $subtitle = null,
        ?string $filterInfo = null,
    ) {
        // Ekspor ribuan baris (Excel maupun PDF) butuh memori lebih besar dari default.
        \App\Services\MasterDataExporter::raiseMemoryLimit('1024M');

        $rows = collect($rows)
            ->values()
            ->map(fn ($row) => array_values(array_map(
                fn ($value) => $value === null ? '' : (string) $value,
                is_array($row) ? $row : iterator_to_array($row)
            )));

        $slug = Str::slug($filename).'-'.now()->format('Ymd-His');

        if (strtolower($format) === 'pdf') {
            // DomPDF berat untuk ribuan baris -> batasi & beri catatan agar memakai filter.
            $pdfLimit = 5000;

            if ($rows->count() > $pdfLimit) {
                $rows = $rows->take($pdfLimit);
                $subtitle = trim(($subtitle ? $subtitle.' — ' : '')."Hanya {$pdfLimit} baris pertama yang ditampilkan; gunakan filter untuk mengekspor bagian tertentu.");
            }

            return Pdf::loadView('exports.table', [
                'title' => $title,
                'subtitle' => $subtitle,
                'filterInfo' => $filterInfo,
                'columns' => $columns,
                'rows' => $rows,
            ])
                ->setPaper('a4', 'landscape')
                ->download($slug.'.pdf');
        }

        $sheetTitle = preg_replace('/[\[\]:*?\/\\\\]/u', '', Str::limit($title, 28, ''));

        return Excel::download(
            new GenericExport($rows, $columns, $sheetTitle ?: 'Data'),
            $slug.'.xlsx'
        );
    }
}
