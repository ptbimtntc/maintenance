<?php

namespace App\Notifications;

use App\Models\LototoActivity;
use App\Models\LototoBoard;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to the employee whose LOTOTO activity got a follow-up from the supervisor: the activity and the supervisor's
 * text. Sent once per newly saved reply, to the employee's own address (never the supervisor's). The link opens that
 * employee's LOTOTO Activity page (read only; a visitor without a login is taken in as the Guest viewer).
 */
class LototoFollowUpReply extends Notification
{
    public function __construct(
        private readonly LototoActivity $activity,
        private readonly string $followUp,
        private readonly ?string $supervisorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->activity->employee;
        $board = LototoBoard::query()->where('code', $this->activity->board)->first();

        return (new MailMessage)
            ->subject('FOLLOW UP LOTOTO - '.$employee->full_name.' (LOTOTO '.$employee->lototo_number.')')
            ->view('emails.lototo-follow-up-reply', [
                'activity' => $this->activity,
                'employee' => $employee,
                'followUp' => trim($this->followUp),
                'supervisorName' => $this->supervisorName,
                'link' => route('safety.lototo.show', array_filter(['employee' => $employee->id, 'board' => $board?->id])),
            ]);
    }
}
