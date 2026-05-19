<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntrepriseCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $entrepriseName,
        public string $super_adminName,
        public string $super_adminEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue sur PrimeGest — ' . $this->entrepriseName,
        );
    }

    public function content(): Content
    {
        return new Content(
            // ✅ view HTML au lieu de markdown pour garder le design personnalisé
            view: 'emails.entreprise.created',
            with: [
                'entrepriseName'   => $this->entrepriseName,
                'super_adminName'  => $this->super_adminName,
                'super_adminEmail' => $this->super_adminEmail,
                'loginUrl'         => config('app.url') . '/login',
            ],
        );
    }
}