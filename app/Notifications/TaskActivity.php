<?php

namespace App\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Generic "something changed on a task you're assigned to" notice (edit, complete, move, unassign, delete). */
class TaskActivity extends Notification
{
    /** @param  string[]  $lines  */
    public function __construct(
        private readonly string $title,
        private readonly string $taskTitle,
        private readonly array $lines,
        private readonly ?string $url,
        private readonly string $recipientName,
    ) {}

    public function via(object $notifiable): array
    {
        // An employee with no login has only an email address, so no in-app inbox to write to.
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => "\"{$this->taskTitle}\": ".implode('; ', $this->lines).'.',
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title.': '.$this->taskTitle)
            ->greeting('Hi '.$this->recipientName.',')
            ->line("Task \"{$this->taskTitle}\":");

        foreach ($this->lines as $line) {
            $mail->line('- '.$line);
        }

        return $this->url ? $mail->action('Open task', $this->url) : $mail;
    }
}
