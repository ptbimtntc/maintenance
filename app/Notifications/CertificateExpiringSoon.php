<?php

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateExpiringSoon extends Notification
{
    public function __construct(private readonly Certificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Certificate expiring soon',
            'message' => "{$this->certificate->name} for {$this->certificate->employee->full_name} expires on {$this->certificate->expiry_date->format('d M Y')}.",
            'url' => route('employees.show', $this->certificate->employee).'?tab=certificates',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('employees.show', $this->certificate->employee).'?tab=certificates';

        return (new MailMessage)
            ->subject('Certificate expiring soon: '.$this->certificate->name)
            ->greeting('Hi '.$notifiable->name.',')
            ->line("{$this->certificate->name} for {$this->certificate->employee->full_name} expires on {$this->certificate->expiry_date->format('d M Y')}.")
            ->action('View certificate', $url)
            ->line('Please arrange recertification before it expires.');
    }
}
