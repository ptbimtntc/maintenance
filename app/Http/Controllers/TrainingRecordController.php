<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Http\Requests\StoreTrainingRecordRequest;
use App\Models\CompetencyLevel;
use App\Models\Employee;
use App\Models\TrainingProgram;
use App\Models\TrainingProvider;
use App\Models\TrainingRecord;
use App\Models\TrainingType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class TrainingRecordController extends Controller
{
    use ExportsSpreadsheet;

    /**
     * Column headers the "Import XLSX" upload is expected to have, in this
     * order. Like Certificates, this always creates new records - a
     * training record has no natural key to match an existing row against.
     */
    private const IMPORT_COLUMNS = [
        'Employee Number', 'Training Program', 'Date', 'Hours', 'Attendance', 'Completion', 'Score',
    ];

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = TrainingRecord::query()
            ->whereHas('employee', fn ($eq) => $eq->visibleTo($request->user()))
            ->with(['employee', 'trainingProgram', 'trainingType'])
            ->when($request->filled('employee_search'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->search($request->string('employee_search')->toString())
            ))
            ->when($request->filled('completion_status'), fn ($q) => $q->where('completion_status', $request->string('completion_status')));

        if ($request->string('export') == 'xlsx') {
            $header = ['Employee', 'Program', 'Date', 'Hours', 'Attendance', 'Completion', 'Score'];
            $rows = $query->orderByDesc('training_date')->get()->map(fn (TrainingRecord $r) => [
                $r->employee->full_name,
                $r->trainingProgram?->title ?? 'Ad-hoc',
                $r->training_date->format('Y-m-d'),
                $r->duration_hours,
                $r->attendance_status,
                $r->completion_status,
                $r->assessment_score,
            ]);

            return $this->streamXlsx('training-records-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
        }

        $records = $query->orderByDesc('training_date')->paginate(20)->withQueryString();

        return view('training.records.index', [
            'records' => $records,
            'completionStatuses' => TrainingRecord::COMPLETION_STATUSES,
            'filters' => $request->only(['employee_search', 'completion_status']),
        ]);
    }

    /**
     * Bulk-creates training records from an uploaded .xlsx file shaped like
     * IMPORT_COLUMNS. Every row always creates a new record.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTrainingRecords->value);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $rows = (new XlsxReader)->load($request->file('file')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = array_combine($header, array_pad($row, count($header), null));

            $employeeNumber = trim((string) ($data['Employee Number'] ?? ''));
            $dateValue = trim((string) ($data['Date'] ?? ''));

            if ($employeeNumber === '' && $dateValue === '') {
                continue;
            }

            if ($employeeNumber === '') {
                $errors[] = "Row {$rowNumber}: Employee Number is required.";

                continue;
            }

            $employee = Employee::query()->visibleTo($request->user())->where('employee_number', $employeeNumber)->first();

            if (! $employee) {
                $errors[] = "Row {$rowNumber}: employee \"{$employeeNumber}\" not found.";

                continue;
            }

            if ($dateValue === '') {
                $errors[] = "Row {$rowNumber}: Date is required.";

                continue;
            }

            try {
                $trainingDate = Carbon::parse($dateValue)->format('Y-m-d');
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: Date \"{$dateValue}\" is not a valid date.";

                continue;
            }

            $trainingProgramId = null;
            $programTitle = trim((string) ($data['Training Program'] ?? ''));

            if ($programTitle !== '' && strcasecmp($programTitle, 'Ad-hoc') !== 0) {
                $program = TrainingProgram::where('title', $programTitle)->first();

                if (! $program) {
                    $errors[] = "Row {$rowNumber}: Training Program \"{$programTitle}\" not found - left as Ad-hoc.";
                } else {
                    $trainingProgramId = $program->id;
                }
            }

            $attendanceValue = strtolower(trim((string) ($data['Attendance'] ?? '')));
            $attendanceStatus = in_array($attendanceValue, TrainingRecord::ATTENDANCE_STATUSES, true) ? $attendanceValue : null;

            if ($attendanceStatus === null) {
                $errors[] = "Row {$rowNumber}: Attendance \"{$data['Attendance']}\" not recognized (expected ".implode('/', TrainingRecord::ATTENDANCE_STATUSES).') - row skipped.';

                continue;
            }

            $completionValue = strtolower(trim((string) ($data['Completion'] ?? '')));
            $completionStatus = in_array($completionValue, TrainingRecord::COMPLETION_STATUSES, true) ? $completionValue : null;

            if ($completionStatus === null) {
                $errors[] = "Row {$rowNumber}: Completion \"{$data['Completion']}\" not recognized (expected ".implode('/', TrainingRecord::COMPLETION_STATUSES).') - row skipped.';

                continue;
            }

            $hours = trim((string) ($data['Hours'] ?? ''));
            $score = trim((string) ($data['Score'] ?? ''));

            $employee->trainingRecords()->create([
                'training_program_id' => $trainingProgramId,
                'training_date' => $trainingDate,
                'duration_hours' => $hours !== '' && is_numeric($hours) ? $hours : null,
                'attendance_status' => $attendanceStatus,
                'completion_status' => $completionStatus,
                'assessment_score' => $score !== '' && is_numeric($score) ? $score : null,
                'recorded_by' => $request->user()->id,
                'record_date' => now(),
            ]);

            $created++;
        }

        $redirect = redirect()->route('training.records.index')
            ->with('status', "{$created} training record(s) created from import.");

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }

    public function create(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $this->authorize(PermissionName::ManageTrainingRecords->value);

        return view('training.records.form', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTrainingRecordRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('view', $employee);

        $employee->trainingRecords()->create([
            ...$request->safe()->all(),
            'certificate_issued' => $request->boolean('certificate_issued'),
            'recorded_by' => $request->user()->id,
            'record_date' => now(),
        ]);

        return redirect()->route('employees.show', array_filter(['employee' => $employee, 'from' => $request->input('from')]))
            ->with('status', 'Training record added.')
            ->with('activeTab', 'training');
    }

    public function destroy(Request $request, Employee $employee, TrainingRecord $record): RedirectResponse
    {
        $this->authorize('view', $employee);
        $this->authorize(PermissionName::ManageTrainingRecords->value);
        abort_unless($record->employee_id === $employee->id, 404);

        $record->delete();

        return redirect()->route('employees.show', array_filter(['employee' => $employee, 'from' => $request->input('from')]))
            ->with('status', 'Training record removed.')
            ->with('activeTab', 'training');
    }

    private function formOptions(): array
    {
        return [
            'programs' => TrainingProgram::where('status', 'active')->orderBy('title')->get(),
            'types' => TrainingType::where('is_active', true)->orderBy('name')->get(),
            'providers' => TrainingProvider::where('is_active', true)->orderBy('name')->get(),
            'competencyLevels' => CompetencyLevel::where('is_active', true)->orderBy('level_number')->get(),
        ];
    }
}
