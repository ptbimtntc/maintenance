<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\News;
use App\Models\User;
use App\Notifications\NewsPublished;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_cannot_view_news_management(): void
    {
        $response = $this->get(route('news.index'));

        $response->assertRedirect('/guest-login');
    }

    public function test_maintenance_staff_cannot_manage_news(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $this->actingAs($staff)->get(route('news.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('news.create'))->assertForbidden();
    }

    public function test_administrator_can_create_news(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $response = $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'New Safety Policy',
            'body' => '<p>Please read the updated policy.</p>',
            'template' => 'standard',
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('news.index'));
        $this->assertDatabaseHas('news', [
            'title' => 'New Safety Policy',
            'created_by' => $admin->id,
            'is_published' => true,
        ]);
    }

    public function test_news_body_is_sanitized_on_create(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'Malicious Post',
            'body' => '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(2)">bad</a>',
            'template' => 'standard',
            'is_published' => '1',
        ]);

        $news = News::where('title', 'Malicious Post')->firstOrFail();

        $this->assertStringNotContainsString('onclick', $news->body);
        $this->assertStringNotContainsString('<script', $news->body);
        $this->assertStringNotContainsString('javascript:', $news->body);
        $this->assertStringContainsString('Hi', $news->body);
    }

    public function test_uploaded_image_is_stored_and_rendered_from_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $image = UploadedFile::fake()->create('banner.jpg', 10, 'image/jpeg');

        $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'With Image',
            'body' => '<p>Body</p>',
            'template' => 'highlight',
            'is_published' => '1',
            'image' => $image,
        ]);

        $news = News::where('title', 'With Image')->firstOrFail();

        $this->assertNotNull($news->image_path);
        Storage::disk('public')->assertExists($news->image_path);
    }

    public function test_deleting_news_removes_its_image_from_storage(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        $path = UploadedFile::fake()->create('banner.jpg', 10, 'image/jpeg')->store('news-images', 'public');
        $news = News::factory()->create(['created_by' => $admin->id, 'image_path' => $path]);

        $this->actingAs($admin)->delete(route('news.destroy', $news));

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('news', ['id' => $news->id]);
    }

    public function test_publishing_a_news_item_notifies_every_other_user_once(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $colleague = User::factory()->create();

        $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'Announcement',
            'body' => '<p>Body</p>',
            'template' => 'standard',
            'is_published' => '1',
        ]);

        $news = News::where('title', 'Announcement')->firstOrFail();

        $this->assertNotNull($news->notified_at);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $colleague->id,
            'type' => NewsPublished::class,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $admin->id,
            'type' => NewsPublished::class,
        ]);
    }

    public function test_editing_an_already_published_news_item_does_not_notify_again(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $colleague = User::factory()->create();

        $news = News::factory()->create([
            'created_by' => $admin->id,
            'is_published' => true,
            'notified_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->put(route('news.update', $news), [
            'title' => 'Updated Title',
            'body' => $news->body,
            'template' => $news->template,
            'is_published' => '1',
        ]);

        $this->assertSame(
            0,
            DatabaseNotification::where('notifiable_id', $colleague->id)->where('type', NewsPublished::class)->count()
        );
    }

    public function test_draft_news_is_excluded_from_the_published_scope(): void
    {
        News::factory()->create(['is_published' => false]);
        $published = News::factory()->create(['is_published' => true]);

        $result = News::published()->get();

        $this->assertTrue($result->contains($published));
        $this->assertCount(1, $result);
    }

    public function test_expired_news_is_excluded_from_the_published_scope(): void
    {
        News::factory()->create(['is_published' => true, 'expires_at' => now()->subDay()]);
        $stillValid = News::factory()->create(['is_published' => true, 'expires_at' => now()->addDay()]);
        $noExpiry = News::factory()->create(['is_published' => true, 'expires_at' => null]);

        $result = News::published()->get();

        $this->assertTrue($result->contains($stillValid));
        $this->assertTrue($result->contains($noExpiry));
        $this->assertCount(2, $result);
    }

    public function test_any_authenticated_user_can_view_a_published_news_item(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $news = News::factory()->create(['is_published' => true]);

        $this->actingAs($staff)->get(route('news.show', $news))->assertOk();
    }

    public function test_non_manager_cannot_view_a_draft_news_item(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $news = News::factory()->create(['is_published' => false]);

        $this->actingAs($staff)->get(route('news.show', $news))->assertNotFound();
    }

    public function test_manager_can_still_preview_a_draft_news_item(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $news = News::factory()->create(['is_published' => false]);

        $this->actingAs($admin)->get(route('news.show', $news))->assertOk();
    }

    public function test_dashboard_carousel_only_shows_published_unexpired_news(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        $visible = News::factory()->create(['title' => 'Visible News', 'is_published' => true]);
        News::factory()->create(['title' => 'Hidden Draft', 'is_published' => false]);
        News::factory()->create(['title' => 'Hidden Expired', 'is_published' => true, 'expires_at' => now()->subDay()]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($visible->title);
        $response->assertDontSee('Hidden Draft');
        $response->assertDontSee('Hidden Expired');
    }
}
