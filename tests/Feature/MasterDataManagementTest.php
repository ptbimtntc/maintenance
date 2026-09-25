<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_organization_section(): void
    {
        $response = $this->get(route('organization.landing'));

        $response->assertRedirect('/guest-login');
    }

    public function test_non_administrator_cannot_manage_master_data(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($staff)->get(route('organization.landing'));

        $response->assertForbidden();
    }

    public function test_administrator_can_view_the_organization_landing_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get(route('organization.landing'));

        $response->assertOk();
        $response->assertSee('Departments');
    }

    public function test_administrator_can_create_a_department(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('organization.store', 'departments'), [
            'name' => 'Quality Assurance',
            'code' => 'QA01',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('organization.index', 'departments'));
        $this->assertDatabaseHas('departments', ['name' => 'Quality Assurance', 'code' => 'QA01']);
    }

    public function test_duplicate_department_code_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        Department::factory()->create(['code' => 'DUP01']);

        $response = $this->actingAs($admin)->post(route('organization.store', 'departments'), [
            'name' => 'Another Department',
            'code' => 'DUP01',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_administrator_can_deactivate_a_department_via_edit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $department = Department::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->put(route('organization.update', ['departments', $department->id]), [
            'name' => $department->name,
            'code' => $department->code,
            // is_active intentionally omitted, simulating an unchecked checkbox
        ]);

        $response->assertRedirect(route('organization.index', 'departments'));
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => false]);
    }

    public function test_administrator_can_delete_a_department(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $department = Department::factory()->create();

        $response = $this->actingAs($admin)->delete(route('organization.destroy', ['departments', $department->id]));

        $response->assertRedirect(route('organization.index', 'departments'));
        $this->assertSoftDeleted($department);
    }

    public function test_administrator_can_create_a_position_with_a_parent_department_and_grade_level(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $department = Department::factory()->create();

        $response = $this->actingAs($admin)->post(route('organization.store', 'positions'), [
            'title' => 'Reliability Engineer',
            'department_id' => $department->id,
            'grade_level' => 3,
        ]);

        $response->assertRedirect(route('organization.index', 'positions'));
        $this->assertDatabaseHas('positions', [
            'title' => 'Reliability Engineer',
            'department_id' => $department->id,
            'grade_level' => 3,
        ]);
    }

    public function test_positions_index_shows_the_parent_department_column(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $department = Department::factory()->create(['name' => 'Utility Department']);
        Position::factory()->create(['department_id' => $department->id, 'title' => 'Utility Technician']);

        $response = $this->actingAs($admin)->get(route('organization.index', 'positions'));

        $response->assertOk();
        $response->assertSee('Utility Technician');
        $response->assertSee('Utility Department');
    }

    public function test_unknown_master_data_type_returns_404(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get('/organization/not-a-real-type');

        $response->assertNotFound();
    }

    /**
     * The "skills", "skill-categories", and "competency-levels" master
     * data types are linked to from two different landing pages
     * (Organization & Master Data, and Skills & Competencies) - the "Back"
     * link must reflect whichever one the visitor actually came from,
     * carried via ?from=skills, rather than always assuming Organization.
     */
    public function test_back_link_defaults_to_organization_when_no_origin_is_given(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get(route('organization.index', 'skills'));

        $response->assertOk();
        $response->assertSee('Back to Organization');
        $response->assertSee(route('organization.landing'), false);
    }

    public function test_back_link_points_to_skills_and_competencies_when_that_is_the_origin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->get(route('organization.index', ['skills', 'from' => 'skills']));

        $response->assertOk();
        $response->assertSee('Back to Skills &amp; Competencies', false);
        $response->assertSee(route('skills.landing'), false);
    }

    public function test_the_skills_origin_survives_through_create_and_save(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $createResponse = $this->actingAs($admin)->get(route('organization.create', ['skills', 'from' => 'skills']));
        $createResponse->assertOk();
        $createResponse->assertSee(route('organization.index', ['skills', 'from' => 'skills']), false);

        $storeResponse = $this->actingAs($admin)->post(route('organization.store', ['skills', 'from' => 'skills']), [
            'name' => 'Origin Test Skill',
            'is_active' => true,
        ]);

        $storeResponse->assertRedirect(route('organization.index', ['skills', 'from' => 'skills']));
    }
}
