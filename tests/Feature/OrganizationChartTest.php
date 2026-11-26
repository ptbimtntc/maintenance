<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\EmploymentStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    private EmploymentStatus $active;

    protected function setUp(): void
    {
        parent::setUp();

        // The chart only lists employees whose status code is ACTIVE.
        $this->active = EmploymentStatus::factory()->create(['code' => 'ACTIVE']);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_the_organization_chart(): void
    {
        $response = $this->get(route('organization-chart.index'));

        $response->assertRedirect('/guest-login');
    }

    public function test_a_user_without_any_employee_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('organization-chart.index'));

        $response->assertForbidden();
    }

    public function test_administrator_sees_the_whole_company_as_root_level_trees(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $ceo = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Top Boss', 'supervisor_id' => null]);
        $manager = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Middle Manager', 'supervisor_id' => $ceo->id]);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Bottom Staff', 'supervisor_id' => $manager->id]);

        $response = $this->actingAs($admin)->get(route('organization-chart.index', ['view' => 'chart']));

        $response->assertOk();
        $response->assertViewHas('roots', fn ($roots) => $roots->pluck('id')->contains($ceo->id));
        $response->assertSee('Top Boss');
        $response->assertSee('Middle Manager');
        $response->assertSee('Bottom Staff');
    }

    public function test_gallery_is_the_default_and_drills_down_one_team_at_a_time(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $ceo = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Top Boss', 'supervisor_id' => null]);
        $manager = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Middle Manager', 'supervisor_id' => $ceo->id]);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Bottom Staff', 'supervisor_id' => $manager->id]);

        // The single top of the chart is the first team; its members are a 3D gallery.
        $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1]))
            ->assertOk()
            ->assertViewHas('view', 'gallery')
            ->assertViewHas('gallery', fn ($gallery) => $gallery['leader']->is($ceo)
                && $gallery['members']->pluck('id')->all() === [$manager->id])
            ->assertSee('id="org-gallery"', false)
            ->assertSee('Team of')
            ->assertSee('Team 1')
            ->assertSee('leader='.$manager->id, false)
            ->assertDontSee('Bottom Staff');

        // Opening the manager's team shows their report, with the reporting line above.
        $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1, 'leader' => $manager->id]))
            ->assertOk()
            ->assertViewHas('gallery', fn ($gallery) => $gallery['leader']->is($manager)
                && $gallery['trail']->pluck('id')->all() === [$ceo->id]
                && $gallery['members']->pluck('full_name')->all() === ['Bottom Staff'])
            ->assertSee('Up one level');
    }

    public function test_gallery_ignores_a_leader_outside_what_the_viewer_may_see(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $mine = Employee::factory()->create(['employment_status_id' => $this->active->id, 'user_id' => $supervisorUser->id, 'full_name' => 'My Self']);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'supervisor_id' => $mine->id, 'full_name' => 'My Report']);
        $otherLead = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Other Lead']);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'supervisor_id' => $otherLead->id, 'full_name' => 'Hidden Report']);

        $this->actingAs($supervisorUser)->get(route('organization-chart.index', ['applied' => 1, 'leader' => $otherLead->id]))
            ->assertOk()
            ->assertViewHas('gallery', fn ($gallery) => ! $gallery['leader']?->is($otherLead))
            ->assertDontSee('Hidden Report')
            ->assertDontSee('Other Lead');
    }

    public function test_gallery_search_shows_the_matches(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $ceo = Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Top Boss', 'supervisor_id' => null]);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Searchable Person', 'supervisor_id' => $ceo->id]);

        $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1, 'search' => 'Searchable']))
            ->assertOk()
            ->assertViewHas('gallery', fn ($gallery) => $gallery['search'] && $gallery['members']->pluck('full_name')->all() === ['Searchable Person'])
            ->assertSee('1 match');
    }

    public function test_a_supervisor_only_sees_themselves_and_direct_reports_in_their_chart(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['employment_status_id' => $this->active->id, 'user_id' => $supervisorUser->id, 'full_name' => 'Sup Person']);

        $directReport = Employee::factory()->create(['employment_status_id' => $this->active->id, 'supervisor_id' => $supervisorEmployee->id, 'full_name' => 'Direct Report']);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'supervisor_id' => $directReport->id, 'full_name' => 'Grandchild Report']);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Unrelated Person']);

        $response = $this->actingAs($supervisorUser)->get(route('organization-chart.index'));

        $response->assertOk();
        $response->assertViewHas('roots', fn ($roots) => $roots->count() === 1 && $roots->first()->id === $supervisorEmployee->id);
        $response->assertSee('Sup Person');
        $response->assertSee('Direct Report');
        $response->assertDontSee('Grandchild Report');
        $response->assertDontSee('Unrelated Person');
    }

    public function test_blue_collar_nodes_render_with_the_blue_background_class(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'Blue Collar Person', 'workforce_category' => 'BC']);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'full_name' => 'White Collar Person', 'workforce_category' => 'WCM']);

        $response = $this->actingAs($admin)->get(route('organization-chart.index'));

        $response->assertOk();
        $content = $response->getContent();

        $bluePos = strpos($content, 'Blue Collar Person');
        $whitePos = strpos($content, 'White Collar Person');
        $this->assertNotFalse($bluePos);
        $this->assertNotFalse($whitePos);

        // Confirm the enclosing node link carries the expected background
        // class for each - look backwards from the name to that node's
        // opening <a> tag.
        $blueNodeStart = strrpos(substr($content, 0, $bluePos), '<a href');
        $whiteNodeStart = strrpos(substr($content, 0, $whitePos), '<a href');

        $this->assertStringContainsString('bg-accent-600', substr($content, $blueNodeStart, $bluePos - $blueNodeStart));
        $this->assertStringContainsString('bg-white', substr($content, $whiteNodeStart, $whitePos - $whiteNodeStart));
    }

    public function test_legend_shows_workforce_totals_for_the_whole_chart(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        Employee::factory()->count(3)->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'BC']);
        Employee::factory()->count(2)->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'WCM']);

        $response = $this->actingAs($admin)->get(route('organization-chart.index'));

        $response->assertOk();
        $response->assertViewHas('workforceCounts', fn ($counts) => $counts->get('BC') === 3 && $counts->get('WCM') === 2);
    }

    public function test_department_filter_narrows_the_chart_and_the_legend_totals(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $maintenance = Department::factory()->create(['is_active' => true]);
        $other = Department::factory()->create(['is_active' => true]);

        Employee::factory()->count(2)->create(['employment_status_id' => $this->active->id, 'department_id' => $maintenance->id, 'workforce_category' => 'BC', 'supervisor_id' => null]);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'department_id' => $other->id, 'workforce_category' => 'BC', 'supervisor_id' => null, 'full_name' => 'Other Dept Person']);

        $response = $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1, 'department_id' => [$maintenance->id]]));

        $response->assertOk();
        $response->assertViewHas('workforceCounts', fn ($counts) => $counts->get('BC') === 2);
        $response->assertDontSee('Other Dept Person');
    }

    public function test_blue_collar_nodes_show_their_trade_and_supervisors_show_team_trade_counts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $supervisor = Employee::factory()->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'WCM', 'supervisor_id' => null]);
        foreach (['MECHANIC', 'Mechanical', 'ELECTRIC', 'Instrumentation', null] as $background) {
            Employee::factory()->create([
                'employment_status_id' => $this->active->id,
                'workforce_category' => 'BC',
                'supervisor_id' => $supervisor->id,
                'technical_background' => $background,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('organization-chart.index', ['view' => 'chart']));

        $response->assertOk();
        $response->assertViewHas('teamTrades', fn ($trades) => $trades->get($supervisor->id) === ['M' => 2, 'E' => 1]);
        $response->assertSee('Team: M 2 · E 1', false);
    }

    public function test_first_visit_defaults_to_tire_cord_and_half_product_in_maintenance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $tireCord = BusinessUnit::factory()->create(['name' => 'TIRE CORD', 'is_active' => true]);
        $halfProduct = BusinessUnit::factory()->create(['name' => 'HALF PRODUCT', 'is_active' => true]);
        $building = BusinessUnit::factory()->create(['name' => 'BUILDING PRODUCT', 'is_active' => true]);
        $maintenance = Department::factory()->create(['name' => 'MAINTENANCE', 'is_active' => true]);
        $utility = Department::factory()->create(['name' => 'UTILITY', 'is_active' => true]);

        $base = ['employment_status_id' => $this->active->id, 'supervisor_id' => null];
        Employee::factory()->create($base + ['full_name' => 'Tire Maint', 'business_unit_id' => $tireCord->id, 'department_id' => $maintenance->id]);
        Employee::factory()->create($base + ['full_name' => 'Half Maint', 'business_unit_id' => $halfProduct->id, 'department_id' => $maintenance->id]);
        Employee::factory()->create($base + ['full_name' => 'Building Maint', 'business_unit_id' => $building->id, 'department_id' => $maintenance->id]);
        Employee::factory()->create($base + ['full_name' => 'Tire Utility', 'business_unit_id' => $tireCord->id, 'department_id' => $utility->id]);

        $response = $this->actingAs($admin)->get(route('organization-chart.index'));

        $response->assertOk();
        $response->assertSee('Tire Maint');
        $response->assertSee('Half Maint');
        $response->assertDontSee('Building Maint');
        $response->assertDontSee('Tire Utility');

        // Submitting the form with nothing ticked means "all", not "defaults".
        $all = $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1]));
        $all->assertSee('Building Maint');
        $all->assertSee('Tire Utility');
    }

    public function test_business_unit_and_department_filters_accept_multiple_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $first = Department::factory()->create(['is_active' => true]);
        $second = Department::factory()->create(['is_active' => true]);
        $third = Department::factory()->create(['is_active' => true]);

        $base = ['employment_status_id' => $this->active->id, 'supervisor_id' => null];
        Employee::factory()->create($base + ['full_name' => 'In First', 'department_id' => $first->id]);
        Employee::factory()->create($base + ['full_name' => 'In Second', 'department_id' => $second->id]);
        Employee::factory()->create($base + ['full_name' => 'In Third', 'department_id' => $third->id]);

        $response = $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1, 'department_id' => [$first->id, $second->id]]));

        $response->assertOk();
        $response->assertSee('In First');
        $response->assertSee('In Second');
        $response->assertDontSee('In Third');
    }

    public function test_employment_type_defaults_to_contract_and_permanent_and_can_be_changed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $permanent = EmploymentType::factory()->create(['name' => 'Permanent', 'is_active' => true]);
        $contract = EmploymentType::factory()->create(['name' => 'Contract', 'is_active' => true]);
        $outsourced = EmploymentType::factory()->create(['name' => 'Outsourced', 'is_active' => true]);

        $base = ['employment_status_id' => $this->active->id, 'supervisor_id' => null];
        Employee::factory()->create($base + ['full_name' => 'Perm Person', 'employment_type_id' => $permanent->id]);
        Employee::factory()->create($base + ['full_name' => 'Contract Person', 'employment_type_id' => $contract->id]);
        Employee::factory()->create($base + ['full_name' => 'Outsourced Person', 'employment_type_id' => $outsourced->id]);

        $default = $this->actingAs($admin)->get(route('organization-chart.index'));
        $default->assertSee('Perm Person');
        $default->assertSee('Contract Person');
        $default->assertDontSee('Outsourced Person');

        $picked = $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1, 'employment_type_id' => [$outsourced->id, $contract->id]]));
        $picked->assertSee('Outsourced Person');
        $picked->assertSee('Contract Person');
        $picked->assertDontSee('Perm Person');
    }

    public function test_a_technician_team_with_a_non_blue_collar_member_still_stacks_in_two_columns(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $supervisor = Employee::factory()->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'WCM', 'supervisor_id' => null]);
        Employee::factory()->count(3)->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'BC', 'supervisor_id' => $supervisor->id]);
        Employee::factory()->create(['employment_status_id' => $this->active->id, 'workforce_category' => 'WCM', 'supervisor_id' => $supervisor->id]);

        $response = $this->actingAs($admin)->get(route('organization-chart.index', ['applied' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('org-chart-stack', $response->getContent());
    }
}
