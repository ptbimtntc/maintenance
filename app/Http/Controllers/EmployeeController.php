<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\BusinessUnit;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentSource;
use App\Models\EmploymentStatus;
use App\Models\EmploymentType;
use App\Models\MaintenanceTeam;
use App\Models\Scopes\ExcludePlantManagerScope;
use App\Models\Position;
use App\Models\Shift;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\SkillPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class EmployeeController extends Controller
{
    use ExportsSpreadsheet;

    /**
     * Column headers shared by export and import, in this exact order, so
     * a file downloaded via "Export XLSX" can be edited and re-uploaded via
     * "Import XLSX" to bulk-update the same employees without reshaping it.
     */
    private const IMPORT_COLUMNS = [
        'Employee Number', 'Nomor LOTOTO', 'ID SAP', 'Full Name', 'Business Unit', 'Department', 'Maintenance Team',
        'Position', 'Skill Position', 'Employment Type', 'Employment Source', 'Management',
        'Employment Status', 'Shift', 'Supervisor (Employee Number)',
        'License Number', 'Preferred Name', 'Gender', 'Date of Birth', 'Email', 'Phone', 'Date Joined',
        'Education', 'Technical Background', 'Years of Experience', 'Notes',
    ];

    /**
     * Plain (non-lookup) import columns => [employees column, validation
     * rules]. Mirrors the rules of the create/edit form so an import can't
     * store anything the form would reject. Date columns are parsed
     * separately (see parseImportDate) before the rules run.
     */
    private function importFieldRules(?int $ignoreEmployeeId): array
    {
        return [
            'Nomor LOTOTO' => ['lototo_number', ['string', 'max:50']],
            'ID SAP' => ['sap_id', ['string', 'max:50', Rule::unique('employees', 'sap_id')->ignore($ignoreEmployeeId)]],
            'License Number' => ['license_number', ['string', 'max:50']],
            'Preferred Name' => ['preferred_name', ['string', 'max:255']],
            'Gender' => ['gender', [Rule::in(['Male', 'Female'])]],
            'Date of Birth' => ['date_of_birth', ['date', 'before:today']],
            'Email' => ['email', ['email', 'max:255', Rule::unique('employees', 'email')->ignore($ignoreEmployeeId)]],
            'Phone' => ['phone', ['string', 'max:30']],
            'Date Joined' => ['date_joined', ['date']],
            'Education' => ['education', ['string', 'max:255']],
            'Technical Background' => ['technical_background', ['string', 'max:255']],
            'Years of Experience' => ['years_of_experience', ['integer', 'min:0', 'max:80']],
            'Notes' => ['notes', ['string', 'max:2000']],
        ];
    }

    /**
     * Turns a raw spreadsheet date cell (an Excel serial number or text like
     * 2024-01-31 / 31/01/2024 / 31-01-2024) into Y-m-d, or null when it
     * can't be read unambiguously. Day-first is assumed for slashed text,
     * and impossible dates (e.g. 31/02/2024) are rejected, not rolled over.
     */
    private function parseImportDate(mixed $value): ?string
    {
        if (is_numeric($value) && (float) $value > 1) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $text);

            if ($date && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);

        $user = $request->user();

        $query = Employee::query()->with([
            'department', 'businessUnit', 'maintenanceArea', 'maintenanceTeam', 'position', 'skillPosition',
            'employmentType', 'employmentSource', 'employmentStatus', 'shift', 'supervisor',
        ]);

        $query->visibleTo($user);

        // The Status filter defaults to "Active" whenever the request
        // doesn't explicitly mention it at all (a fresh visit, or the
        // Reset link) - explicitly picking "All Statuses" sends the field
        // as an empty string, which $request->has() still sees as present,
        // so that choice is respected instead of being overridden back to
        // the default.
        $statusFilterId = $request->has('employment_status_id')
            ? $request->string('employment_status_id')->toString()
            : (string) (EmploymentStatus::where('code', 'ACTIVE')->value('id') ?? '');

        $query->search($request->string('search')->toString())
            ->when($request->filled('business_unit_id'), fn ($q) => $q->where('business_unit_id', $request->integer('business_unit_id')))
            ->when($request->filled('employment_type_id'), fn ($q) => $q->where('employment_type_id', $request->integer('employment_type_id')))
            ->when($request->filled('employment_source_id'), fn ($q) => $q->where('employment_source_id', $request->integer('employment_source_id')))
            ->when($statusFilterId !== '', fn ($q) => $q->where('employment_status_id', $statusFilterId))
            ->when($request->filled('supervisor_id'), fn ($q) => $q->where('supervisor_id', $request->integer('supervisor_id')))
            ->when($request->filled('shift_id'), fn ($q) => $q->where('shift_id', $request->integer('shift_id')));

        if ($request->string('export') == 'xlsx') {
            $rows = $query->orderBy('full_name')->get()->map(fn (Employee $e) => [
                $e->employee_number,
                $e->lototo_number,
                $e->sap_id,
                $e->full_name,
                $e->businessUnit?->name,
                $e->department?->name,
                $e->maintenanceTeam?->name,
                $e->position?->title,
                $e->skillPosition?->name,
                $e->employmentType?->name,
                $e->employmentSource?->name,
                $e->workforce_category,
                $e->employmentStatus?->name,
                $e->shift?->name,
                $e->supervisor?->employee_number,
                $e->license_number,
                $e->preferred_name,
                $e->gender,
                $e->date_of_birth?->format('Y-m-d'),
                $e->email,
                $e->phone,
                $e->date_joined?->format('Y-m-d'),
                $e->education,
                $e->technical_background,
                $e->years_of_experience,
                $e->notes,
            ]);

            return $this->streamXlsx('employees-'.now()->format('Y-m-d').'.xlsx', self::IMPORT_COLUMNS, $rows);
        }

        $filters = [
            ...$request->only([
                'search', 'business_unit_id', 'employment_type_id', 'employment_source_id', 'supervisor_id', 'shift_id',
            ]),
            'employment_status_id' => $statusFilterId,
        ];

        // Built from $filters (not ->withQueryString()): the "All Statuses"
        // option submits employment_status_id as an empty string, which the
        // ConvertEmptyStringsToNull middleware turns into null on the
        // request before pagination links are built. withQueryString()
        // would then drop that null-valued key from page 2+ links, making
        // the "no explicit status" default-to-Active logic above silently
        // reassert itself - which paginates against the wrong result count
        // and shows an empty page 2. $statusFilterId is already resolved to
        // the real string ('' included), so appending it directly keeps
        // "All Statuses" honored across pages.
        $employees = $query->orderBy('full_name')->paginate(15)->appends($filters);

        return view('employees.index', [
            'employees' => $employees,
            'businessUnits' => BusinessUnit::where('is_active', true)->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->get(),
            'employmentSources' => EmploymentSource::where('is_active', true)->orderBy('name')->get(),
            'employmentStatuses' => EmploymentStatus::where('is_active', true)->orderBy('name')->get(),
            'supervisors' => Employee::query()->visibleTo($user)->whereHas('directReports')->orderBy('full_name')->get(['id', 'full_name', 'employee_number']),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(),
            'filters' => $filters,
            'importColumns' => array_values(array_filter(self::IMPORT_COLUMNS, fn ($c) => $c !== 'Employee Number')),
        ]);
    }

    /**
     * A print-friendly grid of every visible employee's certificate
     * verification QR code (see PublicCertificateController::employee()),
     * so an admin can generate and print/download a batch of badges at
     * once instead of opening each employee's profile one by one.
     */
    public function qrCodes(Request $request): View
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $request->only('search');

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with(['department', 'position'])
            ->search($request->string('search')->toString())
            ->orderBy('full_name')
            ->paginate(24)
            ->appends($filters);

        return view('employees.qr-codes', [
            'employees' => $employees,
            'filters' => $filters,
            'qrBaseUrl' => rtrim((string) (Setting::get('qr_base_url') ?: url('/')), '/'),
            'canEditQrBaseUrl' => $request->user()->hasRole(RoleName::Administrator->value),
        ]);
    }

    /**
     * Only the Administrator (superadmin) may change the base URL embedded in
     * every QR code - it's a site-wide value, so it's checked here as well
     * as hidden in the view.
     */
    public function updateQrBaseUrl(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole(RoleName::Administrator->value), 403);

        $data = $request->validate([
            'qr_base_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        Setting::set('qr_base_url', rtrim((string) ($data['qr_base_url'] ?? ''), '/'));

        return back()->with('status', 'QR base URL updated.');
    }

    public function create(): View
    {
        $this->authorize('create', Employee::class);

        return view('employees.create', $this->formOptions());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create([
            ...$request->safe()->except('photo'),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->storeUploadedPhoto($request, $employee);
        $employee->generateOnboardingToken();

        return redirect()->route('employees.show', $employee)->with('status', 'Employee created.');
    }

    /**
     * (Re)issues the QR/link-based onboarding token shown on the employee
     * profile - used when the previous link expired, was already used, or
     * simply wasn't generated (older employees created before this
     * feature existed).
     */
    public function regenerateOnboardingLink(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $employee->generateOnboardingToken();

        return redirect()->route('employees.show', $employee)
            ->with('status', 'A new onboarding link has been generated.');
    }

    public function photo(Employee $employee)
    {
        // Any signed-in user: the same photo is already shown to everyone on the organization chart, tasks and LOTOTO.
        $path = \App\Support\LototoEmployeePhoto::path($employee);
        abort_if($path === null, 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** The photo for public pages: only with the signed link the page itself built. */
    public function signedPhoto(Employee $employee)
    {
        return $this->photo($employee);
    }

    public function show(Employee $employee): View
    {
        $this->authorize('view', $employee);

        $employee->load([
            'department', 'businessUnit', 'maintenanceTeam', 'position', 'skillPosition',
            'employmentType', 'employmentSource', 'employmentStatus', 'shift', 'supervisor',
            'position.skillRequirements.skill', 'position.skillRequirements.requiredCompetencyLevel',
            'skillAssessments' => fn ($q) => $q->with(['skill', 'competencyLevel', 'assessedBy'])->orderByDesc('assessment_date')->orderByDesc('id'),
            'trainingRecords' => fn ($q) => $q->with('trainingProgram')->orderByDesc('training_date'),
            'developmentPlans' => fn ($q) => $q->with(['relatedSkill', 'currentCompetencyLevel', 'targetCompetencyLevel', 'mentor'])->orderByDesc('created_at'),
            'certificates' => fn ($q) => $q->with('certificateType')->orderByDesc('created_at'),
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'currentSkillLevels' => $employee->skillAssessments->unique('skill_id')->keyBy('skill_id'),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'competencyLevels' => CompetencyLevel::where('is_active', true)->orderBy('level_number')->get(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        $this->authorize('update', $employee);

        return view('employees.edit', [
            'employee' => $employee,
            ...$this->formOptions($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update([
            ...$request->safe()->except('photo'),
            'updated_by' => $request->user()->id,
        ]);

        $this->storeUploadedPhoto($request, $employee);

        return redirect()->route('employees.show', array_filter(['employee' => $employee, 'from' => $request->input('from')]))
            ->with('status', 'Employee updated.');
    }

    /**
     * Employee photos are a lower-sensitivity, display-oriented asset
     * (unlike certificates), so they're stored on the public disk and
     * served by direct URL rather than an authorized streaming route.
     */
    private function storeUploadedPhoto(Request $request, Employee $employee): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $path = $request->file('photo')->store('employee-photos', 'public');

        $employee->update(['photo_path' => $path]);
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return redirect()->route('employees.index')->with('status', 'Employee removed.');
    }

    /**
     * Deletes several employees selected via checkboxes on the index page.
     * Each one is re-authorized individually (not just checked against the
     * submitted list) so a user can never delete someone outside both
     * their visibility scope and the EmployeePolicy - the same rule single
     * delete already enforces, just applied per row here.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('employee_ids', []);

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->whereIn('id', is_array($ids) ? $ids : [])
            ->get();

        $deleted = 0;

        foreach ($employees as $employee) {
            if ($request->user()->can('delete', $employee)) {
                $employee->delete();
                $deleted++;
            }
        }

        $message = $deleted > 0 ? "{$deleted} employee(s) removed." : 'No employees were removed.';

        return redirect()->route('employees.index')->with('status', $message);
    }

    /**
     * Bulk-creates and bulk-updates employees from an uploaded .xlsx file
     * shaped like the "Export XLSX" output (see IMPORT_COLUMNS) - matched
     * by Employee Number, which is required and never changed by the
     * import itself. Every other column is optional per row: blank cells
     * leave that field untouched on an update, and a lookup value that
     * doesn't match an existing record (Business Unit, Position, ...) is
     * skipped with a reported error rather than silently creating new
     * master data or guessing.
     *
     * A row whose Employee Number matches an existing employee updates it
     * (respecting visibility - an Employee Number that exists but isn't
     * visible to this user is reported as "not found", never silently
     * updated or duplicated). A row whose Employee Number doesn't exist
     * anywhere creates a new employee, provided Full Name is filled in
     * (required to create a valid record).
     *
     * The optional "columns" input restricts the import to the chosen
     * columns - everything else in the file is ignored, so a partial update
     * (e.g. only Shift) can't touch other fields.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(self::IMPORT_COLUMNS)],
        ]);

        // Only the ticked columns are applied; Employee Number is always
        // used to match the row. No selection at all means every column.
        $selectedColumns = $request->filled('columns')
            ? array_unique(['Employee Number', ...$request->input('columns')])
            : self::IMPORT_COLUMNS;

        $sheet = (new XlsxReader)->load($request->file('file')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        // Unformatted values, so date cells arrive as serial numbers instead
        // of text that depends on the cell's display format.
        $rawRows = $sheet->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);
        array_shift($rawRows);

        $lookups = [
            'Business Unit' => [BusinessUnit::class, 'name', 'business_unit_id'],
            'Department' => [Department::class, 'name', 'department_id'],
            'Maintenance Team' => [MaintenanceTeam::class, 'name', 'maintenance_team_id'],
            'Position' => [Position::class, 'title', 'position_id'],
            'Skill Position' => [SkillPosition::class, 'name', 'skill_position_id'],
            'Employment Type' => [EmploymentType::class, 'name', 'employment_type_id'],
            'Employment Source' => [EmploymentSource::class, 'name', 'employment_source_id'],
            'Employment Status' => [EmploymentStatus::class, 'name', 'employment_status_id'],
            'Shift' => [Shift::class, 'name', 'shift_id'],
        ];

        $created = 0;
        $updated = 0;
        $restored = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for the header row
            $data = array_combine($header, array_slice(array_pad($row, count($header), null), 0, count($header)));
            $data = array_intersect_key($data, array_flip($selectedColumns));
            $rawData = array_combine($header, array_slice(array_pad($rawRows[$index] ?? [], count($header), null), 0, count($header)));

            $employeeNumber = trim((string) ($data['Employee Number'] ?? ''));

            if ($employeeNumber === '') {
                continue;
            }

            $employee = Employee::query()->visibleTo($request->user())->where('employee_number', $employeeNumber)->first();
            $isNew = false;

            if ($employee && ! $request->user()->can('update', $employee)) {
                $errors[] = "Row {$rowNumber}: employee \"{$employeeNumber}\" not found.";

                continue;
            }

            if (! $employee) {
                // A previously deleted employee with this number is brought
                // back (the import is an explicit request for them) and then
                // updated like any other row. The number can't be reused for
                // a fresh record anyway - it's unique even among deleted ones.
                $trashed = Employee::onlyTrashed()->visibleTo($request->user())->where('employee_number', $employeeNumber)->first();

                if ($trashed) {
                    // The policy only recognises live records, so restore
                    // first and put it back if this user couldn't edit it.
                    $trashed->restore();

                    if ($request->user()->can('update', $trashed)) {
                        $employee = $trashed;
                        $restored++;
                    } else {
                        $trashed->delete();
                    }
                }
            }

            if (! $employee) {
                // Exists but outside this user's visibility (e.g. a
                // Supervisor importing a number from another team) -
                // reported the same as "not found", never silently
                // updated or duplicated with a conflicting number.
                if (Employee::withTrashed()->withoutGlobalScope(ExcludePlantManagerScope::class)->where('employee_number', $employeeNumber)->exists()) {
                    $errors[] = "Row {$rowNumber}: employee \"{$employeeNumber}\" not found.";

                    continue;
                }

                $fullName = trim((string) ($data['Full Name'] ?? ''));

                if ($fullName === '') {
                    $errors[] = "Row {$rowNumber}: employee \"{$employeeNumber}\" doesn't exist yet and Full Name is required to create it (make sure the Full Name column is selected and filled) - row skipped.";

                    continue;
                }

                $employee = new Employee(['employee_number' => $employeeNumber]);
                $isNew = true;
            }

            $changes = [];

            if (! empty($data['Full Name'])) {
                $changes['full_name'] = trim((string) $data['Full Name']);
            }

            foreach ($this->importFieldRules($employee->id) as $column => [$field, $rules]) {
                $value = trim((string) ($data[$column] ?? ''));

                if ($value === '') {
                    continue;
                }

                if (in_array($field, ['date_of_birth', 'date_joined'], true)) {
                    $value = $this->parseImportDate($rawData[$column] ?? $value);

                    if ($value === null) {
                        $errors[] = "Row {$rowNumber}: {$column} \"{$data[$column]}\" is not a valid date (use YYYY-MM-DD or DD/MM/YYYY) - left unchanged.";

                        continue;
                    }
                }

                if ($field === 'gender') {
                    $value = ucfirst(strtolower($value));
                }

                $validator = Validator::make([$field => $value], [$field => $rules]);

                if ($validator->fails()) {
                    $errors[] = "Row {$rowNumber}: {$column} - {$validator->errors()->first($field)} - left unchanged.";

                    continue;
                }

                $changes[$field] = $value;
            }

            foreach ($lookups as $column => [$modelClass, $nameField, $foreignKey]) {
                $value = trim((string) ($data[$column] ?? ''));

                if ($value === '') {
                    continue;
                }

                // Plant Manager is managed only from its own menu, so it can't
                // be handed out to a regular employee through the import.
                $match = $modelClass === SkillPosition::class && $value === SkillPosition::PLANT_MANAGER
                    ? null
                    : $modelClass::where($nameField, $value)->first();

                if (! $match) {
                    $errors[] = "Row {$rowNumber}: {$column} \"{$value}\" not found - left unchanged.";

                    continue;
                }

                $changes[$foreignKey] = $match->id;
            }

            $managementValue = strtoupper(trim((string) ($data['Management'] ?? '')));

            if ($managementValue !== '') {
                $code = match (true) {
                    $managementValue === 'BC' || str_starts_with($managementValue, 'BLUE') => 'BC',
                    $managementValue === 'WCM' || str_starts_with($managementValue, 'WHITE') => 'WCM',
                    $managementValue === 'INTERN' || str_starts_with($managementValue, 'INTERN') => 'INTERN',
                    default => null,
                };

                if ($code) {
                    $changes['workforce_category'] = $code;
                } else {
                    $errors[] = "Row {$rowNumber}: Management value \"{$data['Management']}\" not recognized (expected BC, WCM, or INTERN) - left unchanged.";
                }
            }

            $supervisorNumber = trim((string) ($data['Supervisor (Employee Number)'] ?? ''));

            if ($supervisorNumber !== '') {
                $supervisor = Employee::where('employee_number', $supervisorNumber)->first();

                if ($supervisor && $supervisor->id !== $employee->id) {
                    $changes['supervisor_id'] = $supervisor->id;
                } else {
                    $errors[] = "Row {$rowNumber}: supervisor \"{$supervisorNumber}\" not found - left unchanged.";
                }
            }

            if ($isNew) {
                $employee->fill([
                    ...$changes,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ])->save();
                $employee->generateOnboardingToken();
                $created++;
            } elseif ($changes !== []) {
                $employee->update([...$changes, 'updated_by' => $request->user()->id]);
                $updated++;
            }
        }

        $redirect = redirect()->route('employees.index')
            ->with('status', "{$created} employee(s) created, {$updated} updated".($restored ? " ({$restored} of them restored from deleted)" : '').' from import.');

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }

    /**
     * Shared dropdown option lists for the create/edit forms.
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'businessUnits' => BusinessUnit::where('is_active', true)->orderBy('name')->get(),
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(),
            'skillPositions' => SkillPosition::where('is_active', true)->where('name', '!=', SkillPosition::PLANT_MANAGER)->orderBy('name')->get(),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->get(),
            'employmentSources' => EmploymentSource::where('is_active', true)->orderBy('name')->get(),
            'workforceCategories' => Employee::WORKFORCE_CATEGORIES,
            'employmentStatuses' => EmploymentStatus::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->orderBy('name')->get(),
            'possibleSupervisors' => Employee::query()
                ->when($employee, fn ($q) => $q->where('id', '!=', $employee->id))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number']),
        ];
    }
}
