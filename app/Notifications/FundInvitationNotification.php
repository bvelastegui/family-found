<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $invitationUrl)
    {
        $this->onConnection('redis');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invitación al Fondo Familiar')
            ->greeting('Te han invitado al Fondo Familiar')
            ->line('Te invitamos a unirte al Fondo Familiar. Este enlace personal vence en siete días y solo puede usarse una vez.')
            ->action('Aceptar invitación', $this->invitationUrl)
            ->line('Si no esperabas esta invitación, puedes ignorar este mensaje.');
    }
}
