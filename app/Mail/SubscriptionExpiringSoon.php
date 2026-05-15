<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiringSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $entrepriseName,
        public readonly string $plan,
        public readonly string $expireDate,
        public readonly int $joursRestants,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre abonnement '.ucfirst($this->plan)." expire dans {$this->joursRestants} jours"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.expiring-soon',
            with: [
                'userName' => $this->userName,
                'entrepriseName' => $this->entrepriseName,
                'plan' => $this->plan,
                'expireDate' => $this->expireDate,
                'joursRestants' => $this->joursRestants,
                'appUrl' => $this->appUrl,
            ]
        );
    }
}
