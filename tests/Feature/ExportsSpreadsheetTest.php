<?php

namespace Tests\Feature;

use App\Concerns\ExportsSpreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class ExportsSpreadsheetTest extends TestCase
{
    public function test_streamed_xlsx_contains_the_header_and_rows_written_to_it(): void
    {
        $controller = new class
        {
            use ExportsSpreadsheet;

            public function export()
            {
                return $this->streamXlsx('test.xlsx', ['Name', 'Score'], [
                    ['Ade', 90],
                    ['Budi', 75],
                ]);
            }
        };

        $response = $controller->export();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type')
        );

        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($tempFile, $contents);

        $spreadsheet = (new Xlsx)->load($tempFile);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Name', $sheet->getCell('A1')->getValue());
        $this->assertSame('Score', $sheet->getCell('B1')->getValue());
        $this->assertSame('Ade', $sheet->getCell('A2')->getValue());
        $this->assertSame(90, $sheet->getCell('B2')->getValue());
        $this->assertSame('Budi', $sheet->getCell('A3')->getValue());
        $this->assertSame(75, $sheet->getCell('B3')->getValue());

        unlink($tempFile);
    }
}
