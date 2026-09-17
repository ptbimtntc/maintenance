<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_the_organization_chart(): void
    {
        $response = $this->get(route('organization-chart.index'));

        $response->assertRedirect('/login');
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

        $ceo = Employee::factory()->create(['full_name' => 'Top Boss', 'supervisor_id' => null]);
        $manager = Employee::factory()->create(['full_name' => 'Middle Manager', 'supervisor_id' => $ceo->id]);
        Employee::factory()->create(['full_name' => 'Bottom Staff', 'supervisor_id' => $manager->id]);

        $response = $this->actingAs($admin)->get(route('organization-chart.index'));

        $response->assertOk();
        $response->assertViewHas('roots', fn ($roots) => $roots->pluck('id')->contains($ceo->id));
        $response->assertSee('Top Boss');
        $response->assertSee('Middle Manager');
        $response->assertSee('Bottom Staff');
    }

    public function test_a_supervisor_only_sees_themselves_and_direct_reports_in_their_chart(): void
    {
        $supervisorUser = User::factory()->create();
        $supervisorUser->assignRole(RoleName::MaintenanceSupervisor->value);
        $supervisorEmployee = Employee::factory()->create(['user_id' => $supervisorUser->id, 'full_name' => 'Sup Person']);

        $directReport = Employee::factory()->create(['supervisor_id' => $supervisorEmployee->id, 'full_name' => 'Direct Report']);
        Employee::factory()->create(['supervisor_id' => $directReport->id, 'full_name' => 'Grandchild Report']);
        Employee::factory()->create(['full_name' => 'Unrelated Person']);

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

        Employee::factory()->create(['full_name' => 'Blue Collar Person', 'workforce_category' => 'BC']);
        Employee::factory()->create(['full_name' => 'White Collar Person', 'workforce_category' => 'WCM']);

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

        $this->assertStringContainsString('bg-blue-600', substr($content, $blueNodeStart, $bluePos - $blueNodeStart));
        $this->assertStringContainsString('bg-white', substr($content, $whiteNodeStart, $whitePos - $whiteNodeStart));
    }
}
