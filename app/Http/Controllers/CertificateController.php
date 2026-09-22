<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Http\Requests\StoreCertificateRequest;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Employee;
use App\Models\MaintenanceTeam;
use App\Models\Skill;
use App\Models\TrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    use ExportsSpreadsheet;

    private const DISK = 'local';

    /**
     * Column headers the "Import XLSX" upload is expected to have, in this
     * order. Unlike the Employees import, this always creates new
     * certificates (there's no single natural key to match an existing
     * certificate row against) - the file itself still has to be attached
     * afterwards via Edit, since a spreadsheet cell can't carry a PDF/image.
     */
    private const IMPORT_COLUMNS = [
        'Employee Number', 'Certificate Name', 'Certificate Type', 'Number',
        'Issuing Organization', 'Issue Date', 'Expiry Date', 'Verification Status',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $query = Certificate::query()
            ->whereHas('employee', fn ($eq) => $eq->visibleTo($request->user()))
            ->with(['employee', 'certificateType'])
            ->when($request->filled('employee_search'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->search($request->string('employee_search')->toString())
            ))
            ->when($request->filled('certificate_type_id'), fn ($q) => $q->where('certificate_type_id', $request->integer('certificate_type_id')));

        $today = now()->startOfDay();
        $soonCutoff = $today->copy()->addDays(Certificate::expiringSoonDays());

        match ($request->string('status')->toString()) {
            'pending_verification' => $query->where('verification_status', 'pending_verification'),
            'expired' => $query->where('verification_status', 'verified')->whereDate('expiry_date', '<', $today),
            'expiring_soon' => $query->where('verification_status', 'verified')->whereBetween('expiry_date', [$today, $soonCutoff]),
            'no_expiry' => $query->where('verification_status', 'verified')->whereNull('expiry_date'),
            'valid' => $query->where('verification_status', 'verified')->whereDate('expiry_date', '>', $soonCutoff),
            default => null,
        };

        if ($request->string('export') == 'xlsx') {
            $statusLabels = Certificate::statusLabels();

            $header = ['Employee', 'Certificate', 'Type', 'Number', 'Issuing Organization', 'Issue Date', 'Expiry Date', 'Status'];
            $rows = $query->orderByDesc('created_at')->get()->map(fn (Certificate $c) => [
                $c->employee->full_name,
                $c->name,
                $c->certificateType?->name,
                $c->certificate_number,
                $c->issuing_organization,
                $c->issue_date?->format('Y-m-d'),
                $c->expiry_date?->format('Y-m-d'),
                $statusLabels[$c->status()],
            ]);

            return $this->streamXlsx('certificates-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
        }

        $certificates = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('certificates.index', [
            'certificates' => $certificates,
            'certificateTypes' => CertificateType::where('is_active', true)->orderBy('name')->get(),
            'statusLabels' => Certificate::statusLabels(),
            'filters' => $request->only(['employee_search', 'certificate_type_id', 'status']),
        ]);
    }

    private const RECERTIFICATION_WINDOWS = ['30', '60', '90', 'expired', 'all'];

    /**
     * Which certificates are due (or overdue) for renewal, mirroring the
     * legacy system's Recertification report: a "window" of how many days
     * ahead to include (always alongside anything already overdue), plus a
     * scope (team / certificate type / employee search) applied to both the
     * list and the summary counters, so switching windows never changes
     * what "scope" means.
     */
    public function recertification(Request $request): View|StreamedResponse
    {
        $window = $request->string('window')->toString();
        $window = in_array($window, self::RECERTIFICATION_WINDOWS, true) ? $window : '60';

        $scoped = fn () => Certificate::query()
            ->whereHas('employee', fn ($eq) => $eq->visibleTo($request->user()))
            ->where('verification_status', 'verified')
            ->whereNotNull('expiry_date')
            ->when($request->filled('maintenance_team_id'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->where('maintenance_team_id', $request->integer('maintenance_team_id'))
            ))
            ->when($request->filled('certificate_type_id'), fn ($q) => $q->where('certificate_type_id', $request->integer('certificate_type_id')))
            ->when($request->filled('employee_search'), fn ($q) => $q->whereHas(
                'employee',
                fn ($eq) => $eq->search($request->string('employee_search')->toString())
            ));

        $today = now()->startOfDay();

        $query = $scoped()->with(['employee.maintenanceTeam', 'employee.department', 'certificateType']);

        match ($window) {
            'expired' => $query->whereDate('expiry_date', '<', $today),
            '30', '60', '90' => $query->whereDate('expiry_date', '<=', $today->copy()->addDays((int) $window)),
            default => null,
        };

        $summary = [
            'overdue' => $scoped()->whereDate('expiry_date', '<', $today)->count(),
            'due_30' => $scoped()->whereBetween('expiry_date', [$today, $today->copy()->addDays(30)])->count(),
            'due_60' => $scoped()->whereBetween('expiry_date', [$today, $today->copy()->addDays(60)])->count(),
            'due_90' => $scoped()->whereBetween('expiry_date', [$today, $today->copy()->addDays(90)])->count(),
        ];

        if ($request->string('export') == 'xlsx') {
            $header = ['Employee', 'Employee Number', 'Team', 'Department', 'Certificate', 'Type', 'Expiry Date', 'Days Remaining'];
            $rows = $query->orderBy('expiry_date')->get()->map(fn (Certificate $c) => [
                $c->employee->full_name,
                $c->employee->employee_number,
                $c->employee->maintenanceTeam?->name,
                $c->employee->department?->name,
                $c->name,
                $c->certificateType?->name,
                $c->expiry_date?->format('Y-m-d'),
                (int) $today->diffInDays($c->expiry_date, false),
            ]);

            return $this->streamXlsx('recertification-'.now()->format('Y-m-d').'.xlsx', $header, $rows);
        }

        $certificates = $query->orderBy('expiry_date')->paginate(20)->withQueryString();

        return view('certificates.recertification', [
            'certificates' => $certificates,
            'summary' => $summary,
            'window' => $window,
            'maintenanceTeams' => MaintenanceTeam::where('is_active', true)->orderBy('name')->get(),
            'certificateTypes' => CertificateType::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['maintenance_team_id', 'certificate_type_id', 'employee_search']),
        ]);
    }

    /**
     * Bulk-creates certificates from an uploaded .xlsx file shaped like
     * IMPORT_COLUMNS. Each row always creates a new certificate (never
     * updates one) since certificates don't have a single natural key to
     * match against the way an Employee Number does for employees - and the
     * certificate file itself still has to be attached afterwards via Edit,
     * since a spreadsheet cell can't carry a PDF/image upload.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize(PermissionName::ManageCertificates->value);

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
            $name = trim((string) ($data['Certificate Name'] ?? ''));

            if ($employeeNumber === '' && $name === '') {
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

            if ($name === '') {
                $errors[] = "Row {$rowNumber}: Certificate Name is required.";

                continue;
            }

            $certificateTypeId = null;
            $typeName = trim((string) ($data['Certificate Type'] ?? ''));

            if ($typeName !== '') {
                $certificateType = CertificateType::where('name', $typeName)->first();

                if (! $certificateType) {
                    $errors[] = "Row {$rowNumber}: Certificate Type \"{$typeName}\" not found - left blank.";
                } else {
                    $certificateTypeId = $certificateType->id;
                }
            }

            $issueDate = $this->parseImportDate($data['Issue Date'] ?? null, $rowNumber, 'Issue Date', $errors);
            $expiryDate = $this->parseImportDate($data['Expiry Date'] ?? null, $rowNumber, 'Expiry Date', $errors);

            if ($issueDate && $expiryDate && $expiryDate < $issueDate) {
                $errors[] = "Row {$rowNumber}: Expiry Date is before Issue Date - Expiry Date left blank.";
                $expiryDate = null;
            }

            $verificationStatus = 'pending_verification';
            $statusValue = strtolower(trim((string) ($data['Verification Status'] ?? '')));

            if ($statusValue !== '') {
                $verificationStatus = match (true) {
                    str_starts_with($statusValue, 'verified') => 'verified',
                    str_starts_with($statusValue, 'pending') => 'pending_verification',
                    default => null,
                };

                if ($verificationStatus === null) {
                    $errors[] = "Row {$rowNumber}: Verification Status \"{$data['Verification Status']}\" not recognized - defaulted to Pending Verification.";
                    $verificationStatus = 'pending_verification';
                }
            }

            $employee->certificates()->create([
                'certificate_type_id' => $certificateTypeId,
                'name' => $name,
                'certificate_number' => trim((string) ($data['Number'] ?? '')) ?: null,
                'issuing_organization' => trim((string) ($data['Issuing Organization'] ?? '')) ?: null,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'verification_status' => $verificationStatus,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $created++;
        }

        $redirect = redirect()->route('certificates.index')
            ->with('status', "{$created} certificate(s) created from import.");

        if ($errors !== []) {
            $shown = array_slice($errors, 0, 8);
            $suffix = count($errors) > 8 ? ' …and '.(count($errors) - 8).' more.' : '';
            $redirect->with('import_errors', count($errors).' issue(s) found: '.implode(' | ', $shown).$suffix);
        }

        return $redirect;
    }

    private function parseImportDate(mixed $value, int $rowNumber, string $label, array &$errors): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            $errors[] = "Row {$rowNumber}: {$label} \"{$value}\" is not a valid date - left blank.";

            return null;
        }
    }

    public function create(Employee $employee): View
    {
        $this->authorize('view', $employee);
        $this->authorize(PermissionName::ManageCertificates->value);

        return view('certificates.form', [
            'employee' => $employee,
            'certificate' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreCertificateRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('view', $employee);

        $data = $request->safe()->except('file');

        $certificate = $employee->certificates()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->storeUploadedFile($request, $certificate);

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Certificate added.')
            ->with('activeTab', 'certificates');
    }

    public function edit(Employee $employee, Certificate $certificate): View
    {
        $this->authorize('view', $employee);
        $this->authorize(PermissionName::ManageCertificates->value);
        abort_unless($certificate->employee_id === $employee->id, 404);

        return view('certificates.form', [
            'employee' => $employee,
            'certificate' => $certificate,
            ...$this->formOptions(),
        ]);
    }

    public function update(StoreCertificateRequest $request, Employee $employee, Certificate $certificate): RedirectResponse
    {
        $this->authorize('view', $employee);
        abort_unless($certificate->employee_id === $employee->id, 404);

        $certificate->update([
            ...$request->safe()->except('file'),
            'updated_by' => $request->user()->id,
        ]);

        $this->storeUploadedFile($request, $certificate);

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Certificate updated.')
            ->with('activeTab', 'certificates');
    }

    public function destroy(Employee $employee, Certificate $certificate): RedirectResponse
    {
        $this->authorize('view', $employee);
        $this->authorize(PermissionName::ManageCertificates->value);
        abort_unless($certificate->employee_id === $employee->id, 404);

        if ($certificate->file_path) {
            Storage::disk(self::DISK)->delete($certificate->file_path);
        }

        $certificate->delete();

        return redirect()->route('employees.show', $employee)
            ->with('status', 'Certificate removed.')
            ->with('activeTab', 'certificates');
    }

    /**
     * Stream the certificate file to authorized viewers only. The file is
     * never reachable via a public URL - this is the only path to it, and
     * it re-checks the same employee-visibility rules as the profile page.
     */
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('view', $certificate->employee);
        abort_unless($certificate->file_path && Storage::disk(self::DISK)->exists($certificate->file_path), 404);

        return Storage::disk(self::DISK)->download($certificate->file_path, $certificate->file_original_name ?? 'certificate');
    }

    /**
     * The printable certificate sheet (A4 landscape, "Print / Save PDF").
     * Only a verified certificate that has a number is presentable - a
     * pending one is never rendered as if it were valid.
     */
    public function show(Certificate $certificate): View
    {
        $certificate->load(['employee', 'certificateType', 'relatedTrainingProgram']);
        $this->authorize('view', $certificate->employee);

        $available = $certificate->verification_status === 'verified' && filled($certificate->certificate_number);

        $score = $certificate->related_training_program_id
            ? \App\Models\TrainingRecord::where('employee_id', $certificate->employee_id)
                ->where('training_program_id', $certificate->related_training_program_id)
                ->whereNotNull('assessment_score')
                ->orderByDesc('training_date')
                ->value('assessment_score')
            : null;

        return view('certificates.show', ['certificate' => $certificate, 'available' => $available, 'score' => $score]);
    }

    private function storeUploadedFile(Request $request, Certificate $certificate): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        if ($certificate->file_path) {
            Storage::disk(self::DISK)->delete($certificate->file_path);
        }

        $file = $request->file('file');
        $path = $file->store('certificates/'.$certificate->employee_id, self::DISK);

        $certificate->update([
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
        ]);
    }

    private function formOptions(): array
    {
        return [
            'certificateTypes' => CertificateType::where('is_active', true)->orderBy('name')->get(),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
            'trainingPrograms' => TrainingProgram::where('status', 'active')->orderBy('title')->get(),
            'verificationStatuses' => Certificate::VERIFICATION_STATUSES,
        ];
    }
}
