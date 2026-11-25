<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\EmploymentStatus;
use App\Models\SkillPosition;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantManagerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $plantManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->plantManager = Employee::factory()->create([
            'employee_number' => '1',
            'full_name' => 'Plant Boss',
            'supervisor_id' => null,
            'skill_position_id' => SkillPosition::factory()->create(['name' => SkillPosition::PLANT_MANAGER])->id,
        ]);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_plant_manager_is_excluded_from_every_employee_query_and_total(): void
    {
        Employee::factory()->count(3)->create();
        // An employee with no skill position at all must still be counted.
        Employee::factory()->create(['skill_position_id' => null]);

        $this->assertSame(4, Employee::count());
        $this->assertNull(Employee::find($this->plantManager->id));
        $this->assertSame($this->plantManager->id, Employee::plantManager()->id);
    }

    public function test_plant_manager_is_not_listed_or_counted_on_the_employees_page(): void
    {
        Employee::factory()->count(2)->create();
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee('Plant Boss')
            ->assertViewHas('employees', fn ($employees) => $employees->total() === 2);
    }

    public function test_plant_manager_profile_is_not_reachable_through_the_employee_routes(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get(route('employees.show', $this->plantManager->id))->assertNotFound();
        $this->actingAs($admin)->get(route('employees.edit', $this->plantManager->id))->assertNotFound();
    }

    public function test_administrator_can_edit_the_plant_manager_from_the_dedicated_menu(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get(route('plant-manager.edit'))
            ->assertOk()
            ->assertSee('Plant Boss');

        $this->actingAs($admin)->put(route('plant-manager.update'), [
            'employee_number' => '1',
            'full_name' => 'New Plant Boss',
            'email' => 'boss@example.test',
        ])->assertRedirect(route('plant-manager.edit'));

        $fresh = Employee::plantManager();
        $this->assertSame('New Plant Boss', $fresh->full_name);
        $this->assertSame('boss@example.test', $fresh->email);
        // Still the Plant Manager - the edit form can't move them out of it.
        $this->assertSame($this->plantManager->skill_position_id, $fresh->skill_position_id);
        $this->assertNull(Employee::find($this->plantManager->id));
    }

    public function test_editing_the_plant_manager_rejects_a_number_used_by_another_employee(): void
    {
        $other = Employee::factory()->create(['employee_number' => '2']);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->put(route('plant-manager.update'), [
            'employee_number' => $other->employee_number,
            'full_name' => 'Plant Boss',
        ])->assertSessionHasErrors('employee_number');
    }

    public function test_only_users_who_manage_all_employees_can_open_the_plant_manager_menu(): void
    {
        $this->actingAs($this->userWithRole(RoleName::MaintenanceStaff))->get(route('plant-manager.edit'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::MaintenanceStaff))
            ->put(route('plant-manager.update'), ['employee_number' => '1', 'full_name' => 'x'])->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::Guest))->get(route('plant-manager.edit'))->assertRedirect(route('login'));
    }

    public function test_plant_manager_skill_position_cannot_be_assigned_to_a_regular_employee(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->post(route('employees.store'), [
            'employee_number' => '99',
            'full_name' => 'Sneaky',
            'skill_position_id' => $this->plantManager->skill_position_id,
        ])->assertSessionHasErrors('skill_position_id');

        $this->assertSame(0, Employee::where('employee_number', '99')->count());
    }

    public function test_plant_manager_is_missing_from_the_skill_position_dropdown(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get(route('employees.create'))
            ->assertOk()
            ->assertViewHas('skillPositions', fn ($positions) => $positions->doesntContain('name', SkillPosition::PLANT_MANAGER));
    }

    private function activeStatusId(): int
    {
        return EmploymentStatus::factory()->create(['code' => 'ACTIVE', 'counts_as_active' => true])->id;
    }

    public function test_plant_manager_heads_the_organization_chart_above_the_top_leader(): void
    {
        $active = $this->activeStatusId();
        $this->plantManager->update(['employment_status_id' => $active]);
        $leader = Employee::factory()->create([
            'employment_status_id' => $active, 'full_name' => 'Top Leader', 'supervisor_id' => $this->plantManager->id,
        ]);
        $admin = $this->userWithRole(RoleName::Administrator);

        $response = $this->actingAs($admin)->get(route('organization-chart.index'));

        $response->assertOk()
            ->assertViewHas('roots', fn ($roots) => $roots->count() === 1 && $roots->first()->id === $this->plantManager->id)
            ->assertViewHas('childrenByParent', fn ($children) => $children->get($this->plantManager->id)->pluck('id')->all() === [$leader->id])
            ->assertSee('Plant Boss')
            ->assertSee(route('plant-manager.edit'), false)
            ->assertDontSee(route('employees.show', $this->plantManager->id), false);
    }

    public function test_a_supervisor_sees_the_plant_manager_on_their_reporting_line_without_a_link(): void
    {
        $active = $this->activeStatusId();
        $supervisorUser = $this->userWithRole(RoleName::MaintenanceSupervisor);
        $supervisor = Employee::factory()->create([
            'employment_status_id' => $active, 'user_id' => $supervisorUser->id, 'supervisor_id' => $this->plantManager->id,
        ]);
        Employee::factory()->create(['employment_status_id' => $active, 'supervisor_id' => $supervisor->id, 'full_name' => 'Direct Report']);

        $response = $this->actingAs($supervisorUser)->get(route('organization-chart.index'));

        $response->assertOk()
            ->assertViewHas('roots', fn ($roots) => $roots->count() === 1 && $roots->first()->id === $this->plantManager->id)
            ->assertSee('Plant Boss')
            ->assertSee('Direct Report')
            ->assertDontSee(route('plant-manager.edit'), false);
    }

    public function test_searching_the_organization_chart_keeps_the_plant_manager_at_the_top(): void
    {
        $active = $this->activeStatusId();
        $leader = Employee::factory()->create(['employment_status_id' => $active, 'full_name' => 'Findable Person', 'supervisor_id' => $this->plantManager->id]);
        Employee::factory()->create(['employment_status_id' => $active, 'full_name' => 'Someone Else']);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get(route('organization-chart.index', ['search' => 'Findable']))
            ->assertOk()
            ->assertViewHas('roots', fn ($roots) => $roots->pluck('id')->all() === [$this->plantManager->id])
            ->assertSee('Findable Person')
            ->assertDontSee('Someone Else');
    }
}
