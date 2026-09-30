<?php

namespace App\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Small shared helper so every report that offers a formal PDF export (for
 * printing or attaching to an email, unlike the raw-data .xlsx export)
 * does it the same way: a Blade view rendered through the shared letterhead
 * layout (resources/views/reports/pdf/layout.blade.php), landscape A4,
 * downloaded with a dated filename.
 */
trait ExportsPdf
{
    protected function streamPdf(string $filename, string $view, array $data): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
