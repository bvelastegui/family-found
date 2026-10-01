<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class FundActivityNotification extends Notification
{
    public function __construct(public string $title, public string $body, public string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
