<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FundActivityPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $title, public string $body, public string $url)
    {
        $this->afterCommit();
    }

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/pwa-192.png')
            ->data(['url' => $this->url]);
    }
}
