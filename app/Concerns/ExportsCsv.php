<?php

namespace App\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Small shared helper so every report/list that offers a CSV export does it
 * the same way, instead of each controller hand-rolling fputcsv plumbing.
 */
trait ExportsCsv
{
    protected function streamCsv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
