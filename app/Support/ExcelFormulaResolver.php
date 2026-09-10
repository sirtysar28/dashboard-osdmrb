<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pra-proses berkas Excel: hitung seluruh sel formula menjadi nilai murni.
 *
 * Berkas "Data Dashboard.xlsx" instansi memakai formula (mis. =EDATE(P2,48)
 * untuk KENAIKAN PANGKAT). Pembaca default mengembalikan string formula-nya,
 * sehingga perlu dihitung dulu sebelum diimport.
 */
class ExcelFormulaResolver
{
    /**
     * @return string path berkas sementara yang sudah berisi nilai hasil hitungan
     */
    public static function resolve(string $path): string
    {
        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getValue();

                    if (is_string($value) && str_starts_with($value, '=')) {
                        try {
                            $cell->setValue($cell->getCalculatedValue());
                        } catch (\Throwable) {
                            // biarkan apa adanya bila formula gagal dihitung
                        }
                    }
                }
            }
        }

        $temp = tempnam(sys_get_temp_dir(), 'excel-resolved-').'.xlsx';

        (new Xlsx($spreadsheet))->save($temp);

        return $temp;
    }
}
