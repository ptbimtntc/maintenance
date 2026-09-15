<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use Illuminate\Notifications\Notification;

class UpcomingTrainingSession extends Notification
{
    public function __construct(private readonly TrainingSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Upcoming training session',
            'message' => "{$this->session->trainingProgram->title} starts on {$this->session->start_date->format('d M Y')}.",
            'url' => route('training.sessions.show', $this->session),
        ];
    }
}
