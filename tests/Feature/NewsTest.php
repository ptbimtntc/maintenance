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

    public function test_deleting_a_news_item_removes_notifications_that_link_to_it(): void
    {
        // Regression: a notification's url is resolved once at send time
        // and never re-checked - deleting the news it points to used to
        // leave the notification behind, so clicking it 404'd forever.
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);
        $colleague = User::factory()->create();

        $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'Soon Deleted',
            'body' => '<p>Body</p>',
            'template' => 'standard',
            'is_published' => '1',
        ]);
        $news = News::where('title', 'Soon Deleted')->firstOrFail();

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $colleague->id, 'type' => NewsPublished::class]);

        $this->actingAs($admin)->delete(route('news.destroy', $news));

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $colleague->id, 'type' => NewsPublished::class]);
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

    public function test_news_expiring_today_is_still_viewable_all_day(): void
    {
        // Regression: expires_at is cast to a date (midnight), so a naive
        // Carbon::isPast() check on it would call a news item expired the
        // instant its last valid day begins - even though scopePublished()
        // (used by the dashboard carousel) still counts "today" as valid.
        // That mismatch previously made news set to "show until today"
        // appear in the carousel but 404 the moment it was clicked.
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $news = News::factory()->create(['is_published' => true, 'expires_at' => now()->toDateString()]);

        $this->assertTrue(News::published()->get()->contains($news));
        $this->actingAs($staff)->get(route('news.show', $news))->assertOk();
    }

    public function test_any_authenticated_user_can_view_a_published_news_item(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);
        $news = News::factory()->create(['is_published' => true]);

        $this->actingAs($staff)->get(route('news.show', $news))->assertOk();
    }

    public function test_news_page_suggests_only_other_news_the_reader_may_see(): void
    {
        $staff = $this->staffUser();
        $news = News::factory()->create(['title' => 'Current Article']);
        News::factory()->create(['title' => 'Other Published Article']);
        News::factory()->create(['title' => 'Draft Article', 'is_published' => false]);
        $secret = News::factory()->create(['title' => 'Secret Notice', 'requires_acknowledgement' => true]);
        $secret->acknowledgements()->create(['user_id' => $this->staffUser()->id]);

        $this->actingAs($staff)->get(route('news.show', $news))
            ->assertOk()
            ->assertSee('More news')
            ->assertSee('Other Published Article')
            ->assertDontSee('Draft Article')
            ->assertDontSee('Secret Notice');
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

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrator->value);

        return $admin;
    }

    private function staffUser(): User
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::MaintenanceStaff->value);

        return $staff;
    }

    public function test_acknowledgement_news_requires_recipients(): void
    {
        $this->actingAs($this->adminUser())->post(route('news.store'), [
            'title' => 'Important', 'body' => '<p>x</p>', 'template' => 'standard',
            'requires_acknowledgement' => '1',
        ])->assertSessionHasErrors('recipient_ids');
    }

    public function test_acknowledgement_news_is_created_with_recipients_and_only_notifies_them(): void
    {
        $admin = $this->adminUser();
        $recipient = $this->staffUser();
        $other = $this->staffUser();

        $this->actingAs($admin)->post(route('news.store'), [
            'title' => 'Important', 'body' => '<p>x</p>', 'template' => 'standard',
            'is_published' => '1', 'requires_acknowledgement' => '1', 'recipient_ids' => [$recipient->id],
        ])->assertRedirect(route('news.index'));

        $news = News::firstWhere('title', 'Important');
        $this->assertTrue($news->requires_acknowledgement);
        $this->assertDatabaseHas('news_acknowledgements', ['news_id' => $news->id, 'user_id' => $recipient->id, 'acknowledged_at' => null]);
        $this->assertDatabaseMissing('news_acknowledgements', ['news_id' => $news->id, 'user_id' => $other->id]);
        $this->assertCount(1, $recipient->fresh()->notifications);
        $this->assertCount(0, $other->fresh()->notifications);
    }

    public function test_acknowledgement_news_is_hidden_from_non_recipients(): void
    {
        $recipient = $this->staffUser();
        $other = $this->staffUser();
        $news = News::factory()->create(['title' => 'Secret Notice', 'requires_acknowledgement' => true]);
        $news->acknowledgements()->create(['user_id' => $recipient->id]);

        $this->actingAs($other)->get(route('news.show', $news))->assertNotFound();
        $this->actingAs($other)->get(route('dashboard'))->assertDontSee('Secret Notice');
        $this->actingAs($other)->post(route('news.acknowledge', $news))->assertNotFound();

        $this->actingAs($recipient)->get(route('news.show', $news))->assertOk()->assertSee('I have read and understood');
        $this->actingAs($recipient)->get(route('dashboard'))->assertSee('Secret Notice');
    }

    public function test_recipient_can_acknowledge_and_the_time_is_recorded_once(): void
    {
        $recipient = $this->staffUser();
        $news = News::factory()->create(['requires_acknowledgement' => true]);
        $news->acknowledgements()->create(['user_id' => $recipient->id]);

        $this->travelTo(now()->setTime(9, 30, 15));
        $this->actingAs($recipient)->post(route('news.acknowledge', $news))->assertRedirect(route('news.show', $news));
        $first = $news->acknowledgements()->first()->acknowledged_at;
        $this->assertSame('09:30:15', $first->format('H:i:s'));

        $this->travelTo(now()->addDay());
        $this->actingAs($recipient)->post(route('news.acknowledge', $news))->assertRedirect();
        $this->assertTrue($news->acknowledgements()->first()->acknowledged_at->equalTo($first));
    }

    public function test_manager_sees_who_confirmed_and_who_is_pending(): void
    {
        $done = $this->staffUser();
        $pending = $this->staffUser();
        $news = News::factory()->create(['requires_acknowledgement' => true]);
        $news->acknowledgements()->create(['user_id' => $done->id])->forceFill(['acknowledged_at' => now()])->save();
        $news->acknowledgements()->create(['user_id' => $pending->id]);

        $this->actingAs($this->adminUser())->get(route('news.show', $news))
            ->assertOk()->assertSee('Confirmations (1 of 2)')->assertSee($done->name)->assertSee($pending->name);
    }

    public function test_editing_never_removes_a_recipient_who_already_confirmed(): void
    {
        $admin = $this->adminUser();
        $done = $this->staffUser();
        $pending = $this->staffUser();
        $added = $this->staffUser();
        $news = News::factory()->create(['requires_acknowledgement' => true, 'is_published' => true, 'notified_at' => now()]);
        $news->acknowledgements()->create(['user_id' => $done->id])->forceFill(['acknowledged_at' => now()])->save();
        $news->acknowledgements()->create(['user_id' => $pending->id]);

        $this->actingAs($admin)->put(route('news.update', $news), [
            'title' => $news->title, 'body' => '<p>x</p>', 'template' => 'standard',
            'is_published' => '1', 'recipient_ids' => [$added->id],
        ])->assertRedirect(route('news.index'));

        $ids = $news->acknowledgements()->pluck('user_id')->all();
        $this->assertContains($done->id, $ids);
        $this->assertContains($added->id, $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertCount(1, $added->fresh()->notifications);
    }

    public function test_news_type_cannot_be_changed_after_creation(): void
    {
        $news = News::factory()->create();

        $this->actingAs($this->adminUser())->put(route('news.update', $news), [
            'title' => $news->title, 'body' => '<p>x</p>', 'template' => 'standard',
            'is_published' => '1', 'requires_acknowledgement' => '1', 'recipient_ids' => [$this->staffUser()->id],
        ])->assertSessionHasErrors('recipient_ids');

        $this->assertFalse($news->fresh()->requires_acknowledgement);
    }

    public function test_news_form_renders_with_the_recipient_picker(): void
    {
        $admin = $this->adminUser();
        $staff = $this->staffUser();
        $news = News::factory()->create(['requires_acknowledgement' => true]);
        $news->acknowledgements()->create(['user_id' => $staff->id]);

        $this->actingAs($admin)->get(route('news.create'))->assertOk()->assertSee('Requires acknowledgement');
        $this->actingAs($admin)->get(route('news.edit', $news))->assertOk()->assertSee($staff->email);
        $this->actingAs($admin)->get(route('news.index'))->assertOk()->assertSee('0 of 1 confirmed');
    }
}
