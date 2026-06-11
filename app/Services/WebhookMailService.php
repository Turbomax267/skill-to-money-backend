<?php

namespace App\Services;

use App\Mail\WelcomeAccountMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;

class WebhookMailService
{
    public function sendWelcomeMail(User $user, string $frontendUrl, ?string $verificationUrl = null): void
    {
        if (! $this->shouldUseWebhookMailer()) {
            Mail::to($user->email)->send(new WelcomeAccountMail($user, $frontendUrl, $verificationUrl));

            return;
        }

        $this->sendViaWebhook(
            to: $user->email,
            subject: 'Bienvenido a Skill-to-Money',
            html: View::make('emails.welcome-account', [
                'user' => $user,
                'frontendUrl' => $frontendUrl,
                'verificationUrl' => $verificationUrl,
            ])->render(),
            text: $verificationUrl
                ? "Hola {$user->name}, verifica tu correo para continuar en Skill-to-Money: {$verificationUrl}"
                : "Hola {$user->name}, tu cuenta en Skill-to-Money ya esta lista. Ingresa en {$frontendUrl}"
        );
    }

    public function sendPasswordResetMail(User $user, string $url): void
    {
        $this->sendViaWebhook(
            to: $user->email,
            subject: 'Recupera tu acceso a Skill-to-Money',
            html: View::make('emails.password-reset', [
                'user' => $user,
                'url' => $url,
            ])->render(),
            text: "Hola {$user->name}, usa este enlace para restablecer tu contraseña en Skill-to-Money: {$url}"
        );
    }

    public function shouldUseWebhookMailer(): bool
    {
        return config('mail.default') === 'google_apps_script';
    }

    private function sendViaWebhook(string $to, string $subject, string $html, string $text, ?callable $fallback = null): void
    {
        if (! $this->shouldUseWebhookMailer()) {
            if ($fallback !== null) {
                $fallback();
            }

            return;
        }

        $url = (string) config('services.google_apps_script.webhook_url');
        $secret = (string) config('services.google_apps_script.webhook_secret');
        $timeout = (int) config('services.google_apps_script.timeout', 15);

        if ($url === '' || $secret === '') {
            throw new RuntimeException('Falta configurar GOOGLE_MAIL_WEBHOOK_URL o GOOGLE_MAIL_WEBHOOK_SECRET.');
        }

        $response = Http::asJson()
            ->timeout($timeout)
            ->post($url, [
                'secret' => $secret,
                'to' => $to,
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ]);

        if (! $response->successful()) {
            Log::error('Google Apps Script mail webhook failed.', [
                'status' => $response->status(),
                'response_body' => $response->body(),
                'to' => $to,
                'subject' => $subject,
                'mailer' => config('mail.default'),
            ]);

            throw new RuntimeException('No se pudo enviar el correo mediante Google Apps Script.');
        }
    }
}

