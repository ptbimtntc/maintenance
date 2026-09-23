<?php

namespace Tests\Feature;

use App\Enums\MenuKey;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_an_administrator_can_reach_user_management(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_the_user_list_shows_each_users_position_for_context(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $staff = User::factory()->create(['name' => 'Toni Technician']);
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $position = Position::factory()->create(['title' => 'Mechanical Technician']);
        Employee::factory()->create(['user_id' => $staff->id, 'position_id' => $position->id]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Toni Technician');
        $response->assertSee('Mechanical Technician');
    }

    public function test_the_user_list_can_be_filtered_by_search_role_department_and_position(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $mechanicalPosition = Position::factory()->create(['title' => 'Mechanical Technician']);
        $electricalPosition = Position::factory()->create(['title' => 'Electrical Technician']);
        $department = Department::factory()->create(['name' => 'Maintenance']);

        $toni = User::factory()->create(['name' => 'Toni Technician', 'email' => 'toni@example.com']);
        $toni->assignRole(RoleName::MaintenanceStaff->value);
        Employee::factory()->create(['user_id' => $toni->id, 'position_id' => $mechanicalPosition->id, 'department_id' => $department->id]);

        $sam = User::factory()->create(['name' => 'Sam Supervisor', 'email' => 'sam@example.com']);
        $sam->assignRole(RoleName::MaintenanceSupervisor->value);
        Employee::factory()->create(['user_id' => $sam->id, 'position_id' => $electricalPosition->id]);

        // search matches name or email
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'toni']));
        $response->assertSee('Toni Technician')->assertDontSee('Sam Supervisor');

        // role filter
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['role' => RoleName::MaintenanceSupervisor->value]));
        $response->assertSee('Sam Supervisor')->assertDontSee('Toni Technician');

        // department filter
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['department_id' => $department->id]));
        $response->assertSee('Toni Technician')->assertDontSee('Sam Supervisor');

        // position filter
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['position_id' => $electricalPosition->id]));
        $response->assertSee('Sam Supervisor')->assertDontSee('Toni Technician');
    }

    public function test_an_administrator_can_change_a_users_role_and_menu_grants(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $staff), [
            'role' => RoleName::MaintenanceSupervisor->value,
            'menus' => [
                MenuKey::Certificates->value => '1',
            ],
        ]);

        $response->assertRedirect(route('admin.users.edit', $staff));

        $staff->refresh();
        $this->assertTrue($staff->hasRole(RoleName::MaintenanceSupervisor->value));
        $this->assertFalse($staff->hasRole(RoleName::MaintenanceStaff->value));
        $this->assertTrue($staff->canEditMenu(MenuKey::Certificates));
        $this->assertFalse($staff->canEditMenu(MenuKey::Employees));
    }

    public function test_only_an_administrator_can_create_a_user(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);

        $this->actingAs($manager)->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_an_administrator_can_create_a_new_user_with_a_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Budi Baru',
            'email' => 'budi.baru@mpd.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => RoleName::MaintenanceStaff->value,
        ]);

        $newUser = User::where('email', 'budi.baru@mpd.test')->first();
        $response->assertRedirect(route('admin.users.edit', $newUser));

        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole(RoleName::MaintenanceStaff->value));
        $this->assertNotNull($newUser->email_verified_at);

        // The new user can actually log in with the password just set.
        $this->post(route('login'), ['email' => 'budi.baru@mpd.test', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_creating_a_user_requires_matching_password_confirmation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Budi Baru',
            'email' => 'budi.baru@mpd.test',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
            'role' => RoleName::MaintenanceStaff->value,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'budi.baru@mpd.test']);
    }

    public function test_creating_a_user_can_link_it_to_an_unlinked_employee_in_one_step(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $position = Position::factory()->create();
        $employee = Employee::factory()->create(['position_id' => $position->id, 'user_id' => null]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Budi Baru',
            'email' => 'budi.baru@mpd.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => RoleName::MaintenanceStaff->value,
            'employee_id' => $employee->id,
        ]);

        $newUser = User::where('email', 'budi.baru@mpd.test')->firstOrFail();
        $this->assertSame($newUser->id, $employee->fresh()->user_id);
    }

    public function test_the_create_form_only_lists_employees_not_already_linked_to_a_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $existingUser = User::factory()->create();
        $linkedEmployee = Employee::factory()->create(['user_id' => $existingUser->id, 'full_name' => 'Already Linked']);
        $unlinkedEmployee = Employee::factory()->create(['user_id' => null, 'full_name' => 'Not Linked Yet']);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertSee('Not Linked Yet');
        $response->assertDontSee('Already Linked');
    }

    public function test_unchecking_a_menu_persists_as_an_explicit_denial(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MaintenanceManager->value);
        $this->assertTrue($manager->canEditMenu(MenuKey::Employees));

        $this->actingAs($admin)->put(route('admin.users.update', $manager), [
            'role' => RoleName::MaintenanceManager->value,
            'menus' => [],
        ]);

        $this->assertFalse($manager->fresh()->canEditMenu(MenuKey::Employees));
    }
}
