<?php

namespace App\Notifications;

use App\Models\OvertimeEntry;
use Illuminate\Notifications\Notification;

class OvertimeEditRequested extends Notification
{
    public function __construct(private readonly OvertimeEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Overtime edit requested',
            'message' => "{$this->entry->createdBy->name} requested to edit {$this->entry->employee->full_name}'s overtime entry.",
            'url' => route('overtime.index'),
        ];
    }
}
