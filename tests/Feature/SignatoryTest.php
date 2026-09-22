<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Signatory;
use App\Models\TrainingProgram;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_hr_can_create_a_signatory_with_a_signature_image(): void
    {
        Storage::fake('public');
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $response = $this->actingAs($hr)->post(route('signatories.store'), [
            'name' => 'Budi Santoso',
            'title' => 'Maintenance Manager',
            'signature' => UploadedFile::fake()->create('signature.png', 10, 'image/png'),
        ]);

        $response->assertRedirect(route('signatories.index'));
        $signatory = Signatory::where('name', 'Budi Santoso')->first();
        $this->assertNotNull($signatory);
        Storage::disk('public')->assertExists($signatory->signature_path);
    }

    public function test_maintenance_staff_cannot_create_a_signatory(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->post(route('signatories.store'), [
            'name' => 'Someone',
        ]);

        $response->assertForbidden();
    }

    public function test_choosing_a_signatory_on_a_training_program_fills_in_the_signature(): void
    {
        Storage::fake('public');
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $signatory = Signatory::create([
            'name' => 'Ahmad Fauzi',
            'title' => 'Maintenance Manager',
            'signature_path' => 'signatories/ahmad.png',
            'is_active' => true,
        ]);

        $response = $this->actingAs($hr)->post(route('training.programs.store'), [
            'title' => 'Basic Electrical Safety',
            'status' => 'active',
            'authorizer_signatory_id' => $signatory->id,
        ]);

        $response->assertRedirect();
        $program = TrainingProgram::where('title', 'Basic Electrical Safety')->first();
        $this->assertSame('Ahmad Fauzi', $program->authorizer_name);
        $this->assertSame('Maintenance Manager', $program->authorizer_title);
        $this->assertSame('signatories/ahmad.png', $program->authorizer_signature_path);
    }

    public function test_a_manually_uploaded_signature_wins_over_the_chosen_signatory(): void
    {
        Storage::fake('public');
        $hr = User::factory()->create();
        $hr->assignRole(RoleName::PeopleDevelopment->value);

        $signatory = Signatory::create([
            'name' => 'Ahmad Fauzi',
            'signature_path' => 'signatories/ahmad.png',
            'is_active' => true,
        ]);

        $response = $this->actingAs($hr)->post(route('training.programs.store'), [
            'title' => 'Basic Electrical Safety',
            'status' => 'active',
            'authorizer_signatory_id' => $signatory->id,
            'authorizer_signature' => UploadedFile::fake()->create('manual.png', 10, 'image/png'),
        ]);

        $response->assertRedirect();
        $program = TrainingProgram::where('title', 'Basic Electrical Safety')->first();
        $this->assertNotSame('signatories/ahmad.png', $program->authorizer_signature_path);
        Storage::disk('public')->assertExists($program->authorizer_signature_path);
    }
}
