<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    public function __construct(private readonly Task $task, private readonly string $assignerName, private readonly string $recipientName = '') {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New task assigned',
            'message' => "{$this->assignerName} assigned you \"{$this->task->title}\"".($this->task->due_date ? ', due '.$this->task->due_date->format('d M Y') : '').'.',
            'url' => route('tasks.show', $this->task),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Task assigned: '.$this->task->title)
            ->greeting('Hi '.($this->recipientName ?: $notifiable->name).',')
            ->line("{$this->assignerName} assigned you a task in \"{$this->task->plan->name}\": {$this->task->title}".($this->task->due_date ? ' (due '.$this->task->due_date->format('d M Y').')' : '').'.')
            ->action('Open task', route('tasks.show', $this->task));
    }
}
