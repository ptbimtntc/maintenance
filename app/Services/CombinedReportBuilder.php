<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\OvertimeEntry;
use App\Models\TrainingRecord;
use Illuminate\Support\Carbon;

/**
 * Builds the sheet data for the cross-module combined report (Training +
 * Overtime + Certificates) - shared by the on-demand download
 * (ReportController::combinedExport) and the scheduled email
 * (SendCombinedReport), so the two never drift apart on columns or scope.
 *
 * Training and Overtime are scoped to the given period (what happened
 * between periodStart and periodEnd). Certificates aren't period-scoped -
 * "expiring soon" / "expired" is a status as of now, not something that
 * happened during a date range, so it always reflects the current snapshot
 * regardless of period (same definition as Certificate::status()).
 */
class CombinedReportBuilder
{
    /** @return array<string, array{0: array, 1: iterable}> sheet name => [header, rows] */
    public function build(Carbon $periodStart, Carbon $periodEnd): array
    {
        return [
            'Training' => $this->trainingSheet($periodStart, $periodEnd),
            'Overtime' => $this->overtimeSheet($periodStart, $periodEnd),
            'Certificates' => $this->certificatesSheet(),
        ];
    }

    private function trainingSheet(Carbon $periodStart, Carbon $periodEnd): array
    {
        $header = ['Employee', 'Employee Number', 'Program', 'Training Date', 'Attendance', 'Completion', 'Duration (Hours)'];

        $rows = TrainingRecord::query()
            ->whereBetween('training_date', [$periodStart, $periodEnd])
            ->with(['employee', 'trainingProgram'])
            ->orderBy('training_date')
            ->get()
            ->map(fn (TrainingRecord $r) => [
                $r->employee->full_name,
                $r->employee->employee_number,
                $r->trainingProgram?->title,
                $r->training_date->format('Y-m-d'),
                ucfirst($r->attendance_status),
                ucfirst($r->completion_status),
                $r->duration_hours,
            ]);

        return [$header, $rows];
    }

    private function overtimeSheet(Carbon $periodStart, Carbon $periodEnd): array
    {
        $header = ['Employee', 'Employee Number', 'Start', 'End', 'Hours', 'Compensation', 'Status'];

        $rows = OvertimeEntry::query()
            ->whereBetween('start_at', [$periodStart, $periodEnd])
            ->with('employee')
            ->orderBy('start_at')
            ->get()
            ->map(fn (OvertimeEntry $e) => [
                $e->employee->full_name,
                $e->employee->employee_number,
                $e->start_at->format('Y-m-d H:i'),
                $e->end_at->format('Y-m-d H:i'),
                $e->duration_hours,
                OvertimeEntry::compensationLabel($e->compensation_type),
                OvertimeEntry::statusLabel($e->status),
            ]);

        return [$header, $rows];
    }

    private function certificatesSheet(): array
    {
        $header = ['Employee', 'Employee Number', 'Certificate', 'Type', 'Expiry Date', 'Status'];
        $statusLabels = Certificate::statusLabels();

        $rows = Certificate::query()
            ->where('verification_status', 'verified')
            ->whereNotNull('expiry_date')
            ->with(['employee', 'certificateType'])
            ->get()
            ->filter(fn (Certificate $c) => in_array($c->status(), ['expiring_soon', 'expired'], true))
            ->sortBy('expiry_date')
            ->map(fn (Certificate $c) => [
                $c->employee->full_name,
                $c->employee->employee_number,
                $c->name,
                $c->certificateType?->name,
                $c->expiry_date->format('Y-m-d'),
                $statusLabels[$c->status()],
            ]);

        return [$header, $rows];
    }
}
