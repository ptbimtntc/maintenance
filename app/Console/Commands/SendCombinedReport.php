<?php

namespace App\Console\Commands;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Mail\CombinedModuleReport;
use App\Models\User;
use App\Services\CombinedReportBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCombinedReport extends Command
{
    use ExportsSpreadsheet;

    protected $signature = 'app:send-combined-report';

    protected $description = 'Email everyone with "View reports" permission a single workbook combining last month\'s Training, Overtime, and current Certificate status.';

    public function handle(CombinedReportBuilder $builder): int
    {
        $recipients = User::permission(PermissionName::ViewReports->value)->pluck('email');

        if ($recipients->isEmpty()) {
            $this->info('No one holds "View reports" - nothing to send.');

            return self::SUCCESS;
        }

        $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = now()->subMonthNoOverflow()->endOfMonth();

        $path = storage_path('app/combined-report-'.$periodStart->format('Y-m').'-'.uniqid().'.xlsx');
        $this->saveMultiSheetXlsx($path, $builder->build($periodStart, $periodEnd));

        try {
            foreach ($recipients as $email) {
                Mail::to($email)->send(new CombinedModuleReport($periodStart, $periodEnd, $path));
            }
        } finally {
            @unlink($path);
        }

        $this->info("Sent the {$periodStart->format('F Y')} combined report to {$recipients->count()} recipient(s).");

        return self::SUCCESS;
    }
}
