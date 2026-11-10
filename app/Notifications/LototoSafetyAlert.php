<?php

namespace App\Notifications;

use App\Models\LototoActivity;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class LototoSafetyAlert extends Notification
{
    /**
     * @param  list<string>  $unsafeAnswers
     */
    public function __construct(
        private readonly LototoActivity $activity,
        private readonly array $unsafeAnswers,
        private readonly bool $hasFeedback,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
