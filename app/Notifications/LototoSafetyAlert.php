<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\LototoActivity;
use App\Support\LototoFollowUpLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A LOTOTO activity that needs the technician's supervisor: a NO in the checklist and/or feedback from the
 * technician. One notification per activity, to that supervisor only: in the application (the supervisor's account)
 * and by email (the supervisor's employees.email, else the account's email). The email links to the Supervisor Feedback page (LototoFollowUpLink).
 */
class LototoSafetyAlert extends Notification
{
    /**
     * @param  list<string>  $unsafeAnswers
     */
    public function __construct(
        private readonly LototoActivity $activity,
        private readonly array $unsafeAnswers,
        private readonly bool $hasFeedback,
        private readonly ?Employee $supervisor = null,
    ) {}

    /**
     * The supervisor's account gets the in-app notification; the email is sent once, to the address the controller
     * routes it to (the supervisor's employees.email, else the account's email).
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable ? ['mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $items = $this->unsafeAnswers;

        if ($this->hasFeedback) {
            $items[] = 'Feedback dari teknisi';
        }

        return [
            'type' => 'lototo_safety_alert',
            'title' => 'Perlu tindak lanjut LOTOTO',
            'message' => $this->activity->employee->full_name.' (LOTOTO '.$this->activity->employee->lototo_number.'): '.implode(', ', $items)
                .($this->hasFeedback ? ' — '.Str::limit((string) $this->activity->supervisor_feedback, 100) : ''),
            'url' => route('safety.lototo.follow-up', $this->activity),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->activity->employee;

        return (new MailMessage)
            ->subject('LOTOTO & LMRA FEEDBACK - '.$employee->full_name.' (LOTOTO '.$employee->lototo_number.')')
            ->view('emails.lototo-feedback', [
                'activity' => $this->activity,
                'employee' => $employee,
                'supervisor' => $this->supervisor,
                'checklist' => collect(LototoActivity::CHECKLIST)->map(fn ($label, $field) => ['label' => $label, 'yes' => (bool) $this->activity->{$field}])->values(),
                'link' => LototoFollowUpLink::url($this->activity, $this->supervisor),
            ]);
    }
}
