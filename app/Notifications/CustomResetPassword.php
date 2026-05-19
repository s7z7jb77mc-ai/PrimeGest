<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        // $prenom utilisé dans le greeting maintenant
        $prenom = $notifiable->prenom ?? 'Administrateur';

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe PrimeGest')
            ->from(config('mail.from.address'), 'PrimeGest')
            ->view('emails.reset-password', [
                'url' => $url,
                'prenom' => $prenom,
            ]);
    }
}
