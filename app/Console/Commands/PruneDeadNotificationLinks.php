<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\TrainingSession;
use App\Notifications\NewsPublished;
use App\Notifications\UpcomingTrainingSession;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class PruneDeadNotificationLinks extends Command
{
    protected $signature = 'app:prune-dead-notification-links';

    protected $description = 'Delete database notifications (News, Training Session reminders) whose linked record has since been deleted, so clicking an old notification never 404s.';

    public function handle(): int
    {
        $deleted = 0;
        $deleted += $this->pruneFor(NewsPublished::class, fn (int $id) => News::whereKey($id)->exists());
        $deleted += $this->pruneFor(UpcomingTrainingSession::class, fn (int $id) => TrainingSession::whereKey($id)->exists());

        $this->info("Pruned {$deleted} dead notification(s).");

        return self::SUCCESS;
    }

    private function pruneFor(string $type, \Closure $stillExists): int
    {
        $deleted = 0;

        DatabaseNotification::where('type', $type)->each(function (DatabaseNotification $notification) use ($stillExists, &$deleted) {
            $id = (int) Str::afterLast(rtrim((string) ($notification->data['url'] ?? ''), '/'), '/');

            if ($id === 0 || ! $stillExists($id)) {
                $notification->delete();
                $deleted++;
            }
        });

        return $deleted;
    }
}
