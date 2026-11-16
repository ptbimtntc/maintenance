<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\User;
use App\Support\LototoEmployeePhoto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The employee photo on every page that shows it: streamed by the application (no public/storage link), initials when
 * the photo is missing, one avatar component, a preview on the Employee forms, and a signed copy for public pages.
 */
class EmployeePhotoPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $withPhoto;
    private Employee $withoutFile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        LototoEmployeePhoto::forget();
        $this->seed(RolesAndPermissionsSeeder::class);
        $unit = BusinessUnit::factory()->create(['is_active' => true]);
        $active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::Administrator->value);
        Storage::disk('public')->put('employee-photos/a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->withPhoto = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'full_name' => 'Aaa Afif Suryana', 'employee_number' => '261193', 'photo_path' => 'employee-photos/a.png']);
        $this->withoutFile = Employee::factory()->create(['business_unit_id' => $unit->id, 'employment_status_id' => $active->id, 'full_name' => 'Aab Tanpa File', 'photo_path' => 'employee-photos/hilang.png']);
    }

    public function test_employee_pages_use_the_app_photo_route_or_initials_and_never_the_storage_link(): void
    {
        $index = $this->actingAs($this->admin)->get(route('employees.index', ['search' => 'Aa']))->assertOk()->getContent();
        $show = $this->actingAs($this->admin)->get(route('employees.show', $this->withPhoto))->assertOk()->getContent();
        $edit = $this->actingAs($this->admin)->get(route('employees.edit', $this->withPhoto))->assertOk()->getContent();
        foreach ([$index, $show, $edit] as $html) {
            $this->assertStringContainsString('/employees/'.$this->withPhoto->id.'/photo?v=', $html);
            $this->assertStringNotContainsString('/storage/employee-photos', $html);
            $this->assertStringNotContainsString('\1', $html, 'no stray text next to the photo');
        }
        // The shared avatar: object-cover, never shrinks into an oval, falls back to the initials if the image fails to load.
        $this->assertStringContainsString('h-10 w-10 shrink-0 rounded-full object-cover', $index);
        $this->assertStringContainsString('h-20 w-20 shrink-0 rounded-full object-cover', $show);
        $this->assertStringContainsString('data-initials="AA"', $index);
        $this->assertStringContainsString('onerror=', $index);
        // The file that is gone shows initials, not an image.
        $this->assertStringNotContainsString('/employees/'.$this->withoutFile->id.'/photo', $index);
        $this->actingAs($this->admin)->get(route('employees.show', $this->withoutFile))->assertOk()->assertDontSee('/photo?v=', false);
        // Add and edit forms: preview of a newly chosen file.
        foreach ([$edit, $this->actingAs($this->admin)->get(route('employees.create'))->assertOk()->getContent()] as $form) {
            $this->assertStringContainsString('alt="Preview"', $form);
            $this->assertStringContainsString('URL.createObjectURL', $form);
        }
    }

    public function test_any_signed_in_user_gets_the_photo_but_a_visitor_does_not(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $this->actingAs($staff)->get(route('employees.photo', $this->withPhoto))->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs($staff)->get(route('employees.photo', $this->withoutFile))->assertNotFound();
        auth()->logout();
        $this->get(route('employees.photo', $this->withPhoto))->assertRedirect();
    }

    public function test_public_certificate_page_gets_a_signed_photo_that_only_works_unaltered(): void
    {
        $html = $this->get(route('verify.employee', $this->withPhoto))->assertOk()->getContent();
        $this->assertStringNotContainsString('/storage/employee-photos', $html);
        preg_match('#src="(/employee-photos/'.$this->withPhoto->id.'\?[^"]+)"#', $html, $m);
        $this->assertNotEmpty($m, 'signed photo URL on the public page');
        $url = html_entity_decode($m[1]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
        $this->get('/employee-photos/'.$this->withPhoto->id)->assertForbidden();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();
        $this->get(preg_replace('#/employee-photos/\d+#', '/employee-photos/'.$this->withoutFile->id, $url))->assertForbidden();
        // A missing file shows the initial on the public page.
        $this->get(route('verify.employee', $this->withoutFile))->assertOk()->assertSee('photo-placeholder', false);
    }
}
