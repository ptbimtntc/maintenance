<?php

namespace Tests\Feature;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceLoginTest extends TestCase
{
    use RefreshDatabase;

    private function descriptor(float $seed = 0.1): array
    {
        return array_fill(0, 128, $seed);
    }

    public function test_a_user_can_enroll_face_login_on_this_device(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('face-login.enroll'), [
            'descriptor' => $this->descriptor(),
            'consent' => '1',
        ]);

        $response->assertOk();
        $response->assertCookie('face_trusted_device');
        $user->refresh();
        $this->assertTrue($user->hasFaceLoginEnabled());
        $this->assertSame(1, $user->trustedDevices()->count());
    }

    public function test_enrollment_requires_explicit_consent(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('face-login.enroll'), [
            'descriptor' => $this->descriptor(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('consent');
        $this->assertFalse($user->fresh()->hasFaceLoginEnabled());
    }

    public function test_status_endpoint_reports_unavailable_without_a_trusted_device_cookie(): void
    {
        $response = $this->getJson(route('face-login.status'));

        $response->assertOk()->assertJson(['available' => false]);
    }

    public function test_status_endpoint_reports_available_with_a_valid_trusted_device_cookie(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['face_descriptor' => json_encode($this->descriptor()), 'face_enrolled_at' => now()])->save();
        $token = 'plain-text-token';
        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withCredentials()->withUnencryptedCookie('face_trusted_device', $token)->getJson(route('face-login.status'));

        $response->assertOk()->assertJson(['available' => true]);
    }

    public function test_a_matching_descriptor_logs_the_device_owner_in(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['face_descriptor' => json_encode($this->descriptor(0.42)), 'face_enrolled_at' => now()])->save();
        $token = 'plain-text-token';
        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withCredentials()->withUnencryptedCookie('face_trusted_device', $token)
            ->postJson(route('face-login.attempt'), ['descriptor' => $this->descriptor(0.42)]);

        $response->assertOk()->assertJsonStructure(['redirect']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_dissimilar_descriptor_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['face_descriptor' => json_encode($this->descriptor(0.1)), 'face_enrolled_at' => now()])->save();
        $token = 'plain-text-token';
        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withCredentials()->withUnencryptedCookie('face_trusted_device', $token)
            ->postJson(route('face-login.attempt'), ['descriptor' => $this->descriptor(5.0)]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_an_expired_trusted_device_cannot_be_used(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['face_descriptor' => json_encode($this->descriptor()), 'face_enrolled_at' => now()])->save();
        $token = 'plain-text-token';
        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withCredentials()->withUnencryptedCookie('face_trusted_device', $token)
            ->postJson(route('face-login.attempt'), ['descriptor' => $this->descriptor()]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_disabling_face_login_removes_every_trusted_device(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['face_descriptor' => json_encode($this->descriptor()), 'face_enrolled_at' => now()])->save();
        $user->trustedDevices()->create(['token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDays(30)]);
        $user->trustedDevices()->create(['token_hash' => hash('sha256', 'b'), 'expires_at' => now()->addDays(30)]);

        $response = $this->actingAs($user)->post(route('face-login.disable'));

        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->hasFaceLoginEnabled());
        $this->assertSame(0, TrustedDevice::where('user_id', $user->id)->count());
    }
}
