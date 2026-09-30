<?php

namespace App\Concerns;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Database notifications store their destination as a plain "url" string in
 * their JSON payload (see CertificateExpiringSoon, UpcomingTrainingSession,
 * NewsPublished), resolved once at send time rather than looked up live.
 * That's fine right up until the record the link points to gets deleted -
 * the notification keeps existing and clicking it 404s. Call this from a
 * model's destroy() action (with the exact URL that was used to build its
 * notifications) to remove the now-dead links along with it.
 */
trait PrunesNotificationLinks
{
    protected function pruneNotificationsLinkedTo(string $url): void
    {
        DatabaseNotification::where('data->url', $url)->delete();
    }
}
