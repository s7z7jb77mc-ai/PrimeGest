<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $entrepriseName,
        public readonly string $planAncien,
        public readonly string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre abonnement '.ucfirst($this->planAncien).' a expiré — compte repassé en Free'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.expired',
            with: [
                'userName' => $this->userName,
                'entrepriseName' => $this->entrepriseName,
                'planAncien' => $this->planAncien,
                'appUrl' => $this->appUrl,
            ]
        );
    }
}
