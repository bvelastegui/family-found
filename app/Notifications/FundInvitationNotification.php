<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundInvitationNotification extends Notification
{
    public function __construct(public string $invitationUrl) {}

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
            ->line('El tesorero te invita a unirte al fondo. Este enlace personal vence en siete días y solo puede usarse una vez.')
            ->action('Aceptar invitación', $this->invitationUrl)
            ->line('Si no esperabas esta invitación, puedes ignorar este mensaje.');
    }
}
