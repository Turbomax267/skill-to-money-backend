<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $url = $frontendUrl.'/reset-password?token='.urlencode($this->token).'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Recupera tu acceso a Skill-to-Money')
            ->view('emails.password-reset', [
                'user' => $notifiable,
                'url' => $url,
            ]);
    }
}
