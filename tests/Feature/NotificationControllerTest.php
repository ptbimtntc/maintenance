<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_user_can_mark_their_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\CertificateExpiringSoon',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Test', 'message' => 'Test message', 'url' => '/dashboard'],
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect('/dashboard');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);
        $other = User::factory()->create();
        $other->assignRole(RoleName::MaintenanceStaff->value);

        $notification = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\CertificateExpiringSoon',
            'notifiable_type' => User::class,
            'notifiable_id' => $other->id,
            'data' => ['title' => 'Test', 'message' => 'Test message', 'url' => '/dashboard'],
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::MaintenanceStaff->value);
        $other = User::factory()->create();
        $other->assignRole(RoleName::MaintenanceStaff->value);

        $mine = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\CertificateExpiringSoon',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [],
        ]);
        $theirs = DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\CertificateExpiringSoon',
            'notifiable_type' => User::class,
            'notifiable_id' => $other->id,
            'data' => [],
        ]);

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }
}
