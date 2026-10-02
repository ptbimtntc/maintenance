<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\SidebarMenu;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_only_an_administrator_can_manage_menu_visibility(): void
    {
        $manager = $this->userWithRole(RoleName::MaintenanceManager);
        $this->actingAs($manager)->get(route('admin.menu-visibility.index'))->assertForbidden();

        $admin = $this->userWithRole(RoleName::Administrator);
        $this->actingAs($admin)->get(route('admin.menu-visibility.index'))->assertOk();
    }

    public function test_bulk_hide_and_show_across_several_users(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $a = $this->userWithRole(RoleName::MaintenanceStaff);
        $b = $this->userWithRole(RoleName::MaintenanceStaff);

        $this->actingAs($admin)->post(route('admin.menu-visibility.bulk'), [
            'action' => 'hide', 'users' => [$a->id, $b->id], 'menus' => ['overtime', 'lototo'],
        ])->assertRedirect();

        $this->assertTrue($a->fresh()->isMenuHidden(SidebarMenu::Overtime));
        $this->assertTrue($b->fresh()->isMenuHidden(SidebarMenu::Lototo));
        $this->assertFalse($a->fresh()->isMenuHidden(SidebarMenu::Reports));

        $this->actingAs($admin)->post(route('admin.menu-visibility.bulk'), [
            'action' => 'show', 'users' => [$a->id], 'menus' => ['overtime'],
        ]);

        $this->assertFalse($a->fresh()->isMenuHidden(SidebarMenu::Overtime));
        $this->assertTrue($b->fresh()->isMenuHidden(SidebarMenu::Overtime));
    }

    public function test_per_user_update_hides_unchecked_menus(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $staff = $this->userWithRole(RoleName::MaintenanceStaff);

        $this->actingAs($admin)->put(route('admin.menu-visibility.update', $staff), [
            'visible' => ['employees'],
        ])->assertRedirect();

        $staff = $staff->fresh();
        $this->assertFalse($staff->isMenuHidden(SidebarMenu::Employees));
        $this->assertTrue($staff->isMenuHidden(SidebarMenu::Overtime));
    }

    public function test_hidden_menu_is_removed_from_sidebar_and_blocked_by_url(): void
    {
        $staff = $this->userWithRole(RoleName::MaintenanceStaff);

        $this->actingAs($staff)->get(route('shift-comm.index'))->assertOk();
        $this->actingAs($staff)->get(route('dashboard'))->assertSee('Shift Comm');

        $staff->hiddenMenus()->create(['menu_key' => SidebarMenu::ShiftComm->value]);

        $this->actingAs($staff->fresh())->get(route('dashboard'))->assertDontSee('Shift Comm');
        $this->actingAs($staff->fresh())->get(route('shift-comm.index'))->assertRedirect(route('dashboard'));
    }

    public function test_administrators_are_never_affected_by_hidden_menus(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $admin->hiddenMenus()->create(['menu_key' => SidebarMenu::ShiftComm->value]);

        $this->actingAs($admin->fresh())->get(route('shift-comm.index'))->assertOk();
    }
}
