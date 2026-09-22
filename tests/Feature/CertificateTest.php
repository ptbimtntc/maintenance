<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_certificates(): void
    {
        $response = $this->get(route('certificates.index'));

        $response->assertRedirect('/login');
    }

    public function test_maintenance_staff_cannot_add_a_certificate(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $employee = Employee::factory()->create(['user_id' => $staff->id]);

        $response = $this->actingAs($staff)->post(route('employees.certificates.store', $employee), [
            'name' => 'Self-issued Certificate',
            'verification_status' => 'verified',
        ]);

        $response->assertForbidden();
    }

    public function test_hr_can_upload_a_certificate_file_to_private_storage(): void
    {
        Storage::fake('local');
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        $employee = Employee::factory()->create();

        $file = UploadedFile::fake()->create('safety-cert.pdf', 500, 'application/pdf');

        $response = $this->actingAs($hr)->post(route('employees.certificates.store', $employee), [
            'name' => 'Safety Certification',
            'verification_status' => 'verified',
            'file' => $file,
        ]);

        $response->assertRedirect(route('employees.show', $employee));
        $certificate = Certificate::where('name', 'Safety Certification')->first();
        $this->assertNotNull($certificate->file_path);
        Storage::disk('local')->assertExists($certificate->file_path);
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        $employee = Employee::factory()->create();

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($hr)->post(route('employees.certificates.store', $employee), [
            'name' => 'Suspicious File',
            'verification_status' => 'verified',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_expired_certificate_status_is_computed_from_expiry_date(): void
    {
        $certificate = Certificate::factory()->create([
            'expiry_date' => now()->subDay(),
            'verification_status' => 'verified',
        ]);

        $this->assertSame('expired', $certificate->status());
    }

    public function test_certificate_expiring_within_the_window_is_flagged_expiring_soon(): void
    {
        $certificate = Certificate::factory()->create([
            'expiry_date' => now()->addDays(10),
            'verification_status' => 'verified',
        ]);

        $this->assertSame('expiring_soon', $certificate->status());
    }

    public function test_certificate_with_no_expiry_date_is_not_treated_as_expired(): void
    {
        $certificate = Certificate::factory()->create([
            'expiry_date' => null,
            'verification_status' => 'verified',
        ]);

        $this->assertSame('no_expiry', $certificate->status());
    }

    public function test_pending_verification_overrides_expiry_based_status(): void
    {
        $certificate = Certificate::factory()->create([
            'expiry_date' => now()->addYear(),
            'verification_status' => 'pending_verification',
        ]);

        $this->assertSame('pending_verification', $certificate->status());
    }

    public function test_staff_cannot_download_another_employees_certificate(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $staff->id]);

        $otherEmployee = Employee::factory()->create();
        $certificate = Certificate::factory()->create(['employee_id' => $otherEmployee->id, 'file_path' => 'certificates/1/fake.pdf']);

        $response = $this->actingAs($staff)->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    }

    public function test_deleting_a_certificate_removes_its_file_from_storage(): void
    {
        Storage::fake('local');
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);
        $employee = Employee::factory()->create();

        $file = UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf');
        $this->actingAs($hr)->post(route('employees.certificates.store', $employee), [
            'name' => 'To Be Deleted',
            'verification_status' => 'verified',
            'file' => $file,
        ]);

        $certificate = Certificate::where('name', 'To Be Deleted')->first();
        $path = $certificate->file_path;

        $this->actingAs($hr)->delete(route('employees.certificates.destroy', [$employee, $certificate]));

        Storage::disk('local')->assertMissing($path);
    }

    public function test_guest_can_view_an_employees_verified_certificates_via_the_public_verification_page(): void
    {
        $employee = Employee::factory()->create(['employee_number' => 'EMP-260792']);
        $verified = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Forklift Operator',
            'verification_status' => 'verified',
            'certificate_number' => 'CERT-001',
        ]);
        $pending = Certificate::factory()->create([
            'employee_id' => $employee->id,
            'name' => 'Welding Level 2',
            'verification_status' => 'pending_verification',
        ]);

        $response = $this->get(route('verify.employee', $employee));

        $response->assertOk();
        $response->assertSee('Forklift Operator');
        $response->assertDontSee('Welding Level 2');
    }

    public function test_guest_can_view_a_verified_certificates_detail_page_but_not_an_unverified_one(): void
    {
        $verified = Certificate::factory()->create([
            'verification_status' => 'verified',
            'certificate_number' => 'CERT-002',
        ]);
        $pending = Certificate::factory()->create(['verification_status' => 'pending_verification']);

        $this->get(route('verify.certificate', $verified))->assertOk();
        $this->get(route('verify.certificate', $pending))->assertNotFound();
    }

    public function test_guest_can_view_the_printable_certificate_only_once_verified_and_numbered(): void
    {
        $verified = Certificate::factory()->create([
            'verification_status' => 'verified',
            'certificate_number' => 'CERT-003',
        ]);
        $verifiedWithoutNumber = Certificate::factory()->create([
            'verification_status' => 'verified',
            'certificate_number' => null,
        ]);

        $this->get(route('verify.certificate.print', $verified))->assertOk();
        $this->get(route('verify.certificate.print', $verifiedWithoutNumber))->assertNotFound();
    }

    public function test_certificate_detail_page_links_to_the_printable_certificate_when_numbered(): void
    {
        $certificate = Certificate::factory()->create([
            'verification_status' => 'verified',
            'certificate_number' => 'CERT-004',
        ]);

        $response = $this->get(route('verify.certificate', $certificate));

        $response->assertSee(route('verify.certificate.print', $certificate));
    }
}
