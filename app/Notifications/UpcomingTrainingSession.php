<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingTrainingSession extends Notification
{
    public function __construct(private readonly TrainingSession $session) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Upcoming training session',
            'message' => "{$this->session->trainingProgram->title} starts on {$this->session->start_date->format('d M Y')}.",
            'url' => route('training.sessions.show', $this->session),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Upcoming training: '.$this->session->trainingProgram->title)
            ->greeting('Hi '.$notifiable->name.',')
            ->line("{$this->session->trainingProgram->title} starts on {$this->session->start_date->format('d M Y')}".($this->session->location ? " at {$this->session->location->name}" : '').'.')
            ->action('View session', route('training.sessions.show', $this->session))
            ->line('Please make sure to attend on time.');
    }
}
