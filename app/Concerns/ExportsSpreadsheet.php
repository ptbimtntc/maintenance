<?php

namespace App\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Small shared helper so every report/list that offers an Excel export does
 * it the same way: (filename, header, rows) in, a streamed .xlsx download
 * out. .xlsx is the only export format the app offers.
 */
trait ExportsSpreadsheet
{
    protected function streamXlsx(string $filename, array $header, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($header, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        for ($i = 1, $last = Coordinate::columnIndexFromString($sheet->getHighestColumn()); $i <= $last; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
