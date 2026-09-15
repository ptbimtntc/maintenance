<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsCsv;
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
use Illuminate\View\View;

class TrainingRecordController extends Controller
{
    use ExportsCsv, ExportsSpreadsheet;

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = TrainingRecord::query()
            ->with(['employee', 'trainingProgram', 'trainingType'])
            ->when($request->filled('employee_search'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->search($request->string('employee_search')->toString())
            ))
            ->when($request->filled('completion_status'), fn ($q) => $q->where('completion_status', $request->string('completion_status')));

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'])) {
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

            $basename = 'training-records-'.now()->format('Y-m-d');

            return $request->string('export') == 'xlsx'
                ? $this->streamXlsx("{$basename}.xlsx", $header, $rows)
                : $this->streamCsv("{$basename}.csv", $header, $rows);
        }

        $records = $query->orderByDesc('training_date')->paginate(20)->withQueryString();

        return view('training.records.index', [
            'records' => $records,
            'completionStatuses' => TrainingRecord::COMPLETION_STATUSES,
            'filters' => $request->only(['employee_search', 'completion_status']),
        ]);
    }

    public function create(Employee $employee): View
    {
        $this->authorize(PermissionName::ManageTrainingRecords->value);

        return view('training.records.form', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreTrainingRecordRequest $request, Employee $employee): RedirectResponse
    {
        $employee->trainingRecords()->create([
            ...$request->safe()->all(),
            'certificate_issued' => $request->boolean('certificate_issued'),
            'recorded_by' => $request->user()->id,
            'record_date' => now(),
        ]);

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Training record added.')
            ->with('activeTab', 'training');
    }

    public function destroy(Employee $employee, TrainingRecord $record): RedirectResponse
    {
        $this->authorize(PermissionName::ManageTrainingRecords->value);
        abort_unless($record->employee_id === $employee->id, 404);

        $record->delete();

        return redirect()->route('employees.show', $employee)
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
