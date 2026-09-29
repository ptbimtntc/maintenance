<?php

namespace App\Notifications;

use App\Models\News;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewsPublished extends Notification
{
    public function __construct(private readonly News $news) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New announcement: '.$this->news->title,
            'message' => Str::limit(strip_tags($this->news->body), 120),
            'url' => route('news.show', $this->news),
        ];
    }
}
