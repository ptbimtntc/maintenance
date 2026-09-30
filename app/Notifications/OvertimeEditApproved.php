<?php

namespace App\Notifications;

use App\Models\OvertimeEntry;
use Illuminate\Notifications\Notification;

class OvertimeEditApproved extends Notification
{
    public function __construct(private readonly OvertimeEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Overtime edit approved',
            'message' => "Your edit request for {$this->entry->employee->full_name}'s overtime entry was approved. You can now update it.",
            'url' => route('overtime.edit', $this->entry),
        ];
    }
}
