@component('mail::message')

# {{ $isTrial ? "Votre essai gratuit est activé !" : "Abonnement confirmé ✓" }}

Bonjour **{{ $userName }}**,

@if($isTrial)
Votre essai gratuit du plan **{{ ucfirst($plan) }}** pour **{{ $entrepriseName }}** a été activé.

| Détail | Valeur |
|--------|--------|
| Plan | {{ ucfirst($plan) }} |
| Durée | {{ $trialDays }} jours gratuits |
| Expiration | {{ $expireDate }} |
| Montant | Gratuit |

Profitez de toutes les fonctionnalités premium pendant cette période !
@else
Votre abonnement au plan **{{ ucfirst($plan) }}** pour **{{ $entrepriseName }}** a été confirmé avec succès.

| Détail | Valeur |
|--------|--------|
| Plan | {{ ucfirst($plan) }} |
| Montant | {{ $amount }} $/mois |
| Activation | {{ now()->format('d/m/Y') }} |
| Expiration | {{ $expireDate }} |

Merci pour votre confiance !
@endif

@component('mail::button', ['url' => config('app.url') . '/dashboard', 'color' => 'primary'])
Accéder à mon dashboard
@endcomponent

L'équipe PrimeGest

@endcomponent
