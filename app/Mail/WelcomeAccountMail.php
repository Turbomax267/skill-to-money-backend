<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $frontendUrl,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Bienvenido a Skill-to-Money')
            ->view('emails.welcome-account');
    }
}
