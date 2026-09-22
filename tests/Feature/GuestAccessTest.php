<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(DemoUsersSeeder::class);
    }

    public function test_the_guest_login_button_logs_the_visitor_into_the_shared_guest_account(): void
    {
        $response = $this->post(route('guest-login'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->hasRole(\App\Enums\RoleName::Guest->value));
    }

    public function test_guest_can_view_employees_across_the_whole_organization(): void
    {
        Employee::factory()->count(3)->create();
        $expectedTotal = Employee::count();

        $this->post(route('guest-login'));

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $response->assertViewHas('employees', fn ($employees) => $employees->total() === $expectedTotal);
    }

    public function test_guest_cannot_create_an_employee(): void
    {
        $this->post(route('guest-login'));

        $this->get(route('employees.create'))->assertForbidden();
    }

    public function test_guest_cannot_reach_settings_or_user_management(): void
    {
        $this->post(route('guest-login'));

        $this->get(route('settings.edit'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    /**
     * Signatories stays out of reach - it's the operational list of who
     * signs certificates, not company data to browse.
     */
    public function test_guest_cannot_reach_signatories(): void
    {
        $this->post(route('guest-login'));

        $this->get(route('signatories.index'))->assertForbidden();
    }

    public function test_guest_does_not_see_signatories_in_the_sidebar(): void
    {
        $this->post(route('guest-login'));

        $response = $this->get(route('dashboard'));

        $response->assertDontSee('Signatories');
    }

    /**
     * QR Codes is fine for Guest - it's how a visitor without their own
     * account can grab an employee's certificate-verification QR to try
     * out the public /verify/... page.
     */
    public function test_guest_can_reach_the_qr_codes_page(): void
    {
        $this->post(route('guest-login'));

        $this->get(route('employees.qr-codes'))->assertOk();
        $this->get(route('dashboard'))->assertSee('QR Codes');
    }

    public function test_a_manager_still_sees_signatories_and_qr_codes(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(\App\Enums\RoleName::PeopleDevelopment->value);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertSee('Signatories');
        $response->assertSee('QR Codes');
    }

    public function test_guest_cannot_edit_the_shared_profile(): void
    {
        $this->post(route('guest-login'));

        $this->patch(route('profile.update'), ['name' => 'Hacked', 'email' => 'hacked@example.com'])
            ->assertForbidden();
    }

    public function test_menu_edit_override_can_never_make_guest_an_editor(): void
    {
        $guest = User::whereHas('roles', fn ($q) => $q->where('name', \App\Enums\RoleName::Guest->value))->firstOrFail();
        $guest->menuPermissions()->create(['menu_key' => \App\Enums\MenuKey::Employees->value, 'can_edit' => true]);

        $this->assertFalse($guest->fresh()->canEditMenu(\App\Enums\MenuKey::Employees));
    }
}
