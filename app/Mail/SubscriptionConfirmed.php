<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $entrepriseName,
        public string $plan,
        public string $expireDate,
        public float  $amount,
        public bool   $isTrial = false,
        public int    $trialDays = 0,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isTrial
            ? "Votre essai gratuit PrimeGest " . ucfirst($this->plan) . " est activé !"
            : "Votre abonnement PrimeGest " . ucfirst($this->plan) . " est confirmé ✓";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-confirmed');
    }
}
