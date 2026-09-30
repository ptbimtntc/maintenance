<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\News;
use App\Models\TrainingParticipant;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\NewsPublished;
use App\Notifications\UpcomingTrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class PruneDeadNotificationLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_news_notifications_whose_news_item_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $news = News::factory()->create();
        $user->notify(new NewsPublished($news));
        $news->delete();

        $this->artisan('app:prune-dead-notification-links')->assertSuccessful();

        $this->assertSame(0, DatabaseNotification::where('notifiable_id', $user->id)->where('type', NewsPublished::class)->count());
    }

    public function test_it_keeps_news_notifications_whose_news_item_still_exists(): void
    {
        $user = User::factory()->create();
        $news = News::factory()->create();
        $user->notify(new NewsPublished($news));

        $this->artisan('app:prune-dead-notification-links')->assertSuccessful();

        $this->assertSame(1, DatabaseNotification::where('notifiable_id', $user->id)->where('type', NewsPublished::class)->count());
    }

    public function test_it_deletes_training_session_notifications_whose_session_no_longer_exists(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $session = TrainingSession::factory()->create();
        TrainingParticipant::factory()->create(['employee_id' => $employee->id, 'training_session_id' => $session->id]);
        $user->notify(new UpcomingTrainingSession($session));
        $session->delete();

        $this->artisan('app:prune-dead-notification-links')->assertSuccessful();

        $this->assertSame(0, DatabaseNotification::where('notifiable_id', $user->id)->where('type', UpcomingTrainingSession::class)->count());
    }
}
