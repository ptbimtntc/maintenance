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
        $writer = new Xlsx($this->buildSpreadsheet(['Sheet1' => [$header, $rows]]));

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Same idea as streamXlsx(), but for a workbook with one sheet per
     * module (e.g. a combined cross-module report) rather than a single
     * list - and streamed straight to the browser rather than saved to
     * disk first.
     *
     * @param  array<string, array{0: array, 1: iterable}>  $sheets  sheet name => [header, rows]
     */
    protected function streamMultiSheetXlsx(string $filename, array $sheets): StreamedResponse
    {
        $writer = new Xlsx($this->buildSpreadsheet($sheets));

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Same workbook builder, saved to an actual file instead of streamed -
     * for contexts with no HTTP response to stream into, such as a
     * scheduled command attaching the file to an email.
     *
     * @param  array<string, array{0: array, 1: iterable}>  $sheets  sheet name => [header, rows]
     */
    protected function saveMultiSheetXlsx(string $path, array $sheets): void
    {
        (new Xlsx($this->buildSpreadsheet($sheets)))->save($path);
    }

    /** @param  array<string, array{0: array, 1: iterable}>  $sheets  sheet name => [header, rows] */
    private function buildSpreadsheet(array $sheets): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => [$header, $rows]) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle(substr($title, 0, 31)); // Excel sheet-name limit

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
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
