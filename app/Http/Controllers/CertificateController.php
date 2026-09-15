<?php

namespace App\Http\Controllers;

use App\Concerns\ExportsCsv;
use App\Concerns\ExportsSpreadsheet;
use App\Enums\PermissionName;
use App\Http\Requests\StoreCertificateRequest;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Employee;
use App\Models\Skill;
use App\Models\TrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    use ExportsCsv, ExportsSpreadsheet;

    private const DISK = 'local';

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

        if (in_array($request->string('export')->toString(), ['csv', 'xlsx'])) {
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

            $basename = 'certificates-'.now()->format('Y-m-d');

            return $request->string('export') == 'xlsx'
                ? $this->streamXlsx("{$basename}.xlsx", $header, $rows)
                : $this->streamCsv("{$basename}.csv", $header, $rows);
        }

        $certificates = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('certificates.index', [
            'certificates' => $certificates,
            'certificateTypes' => CertificateType::where('is_active', true)->orderBy('name')->get(),
            'statusLabels' => Certificate::statusLabels(),
            'filters' => $request->only(['employee_search', 'certificate_type_id', 'status']),
        ]);
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
