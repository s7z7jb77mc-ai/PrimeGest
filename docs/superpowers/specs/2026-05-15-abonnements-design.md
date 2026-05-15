# Système d'abonnements — Design
**Date :** 2026-05-15
**Contexte :** Paiement manuel maintenant, webhook agrégateur plus tard (double mode)
**Expiration :** Downgrade automatique vers free + emails d'avertissement

---

## 1. État de l'existant

### Déjà fonctionnel
- `app/Models/Subscription.php` — colonnes : plan, amount, payment_method, payment_reference, status (pending/confirmed/expired), starts_at, expires_at, confirmed_by
- `entreprises.plan` (enum free/premium/pro) + `entreprises.plan_expires_at`
- `app/Http/Controllers/Admin/PlanController::activate()` — activation manuelle + email `SubscriptionConfirmed`
- `app/Http/Controllers/Admin/PlanController::downgrade()` — remise en free manuelle
- `app/Mail/SubscriptionConfirmed.php` — email de confirmation

### Manquant
1. Job quotidien de downgrade automatique à l'expiration
2. Email d'avertissement 7 jours avant expiration
3. Email d'expiration (au moment du downgrade auto)
4. Middleware `EnsurePlanNotExpired` pour déclencher le downgrade immédiat à la connexion
5. Page "Mon abonnement" côté entreprise (voir état + soumettre demande)
6. `ActivateSubscriptionAction` — point d'entrée unique pour les deux modes (manuel + webhook)
7. Webhook endpoint placeholder pour l'agrégateur futur

---

## 2. Architecture

```
Mode manuel (maintenant)
  Owner dashboard → Admin/PlanController::activate()
      → ActivateSubscriptionAction
      → entreprise.plan mis à jour + Subscription créée + email

Mode automatique (futur)
  Agrégateur paiement → POST /api/v1/payment/webhook
      → Validation signature HMAC
      → ActivateSubscriptionAction (même action)
      → même résultat

Expiration
  Scheduler quotidien → CheckSubscriptionsExpiration command
      → downgrade si plan_expires_at < now()
      → email expiration
      → avertissement si expires_at dans 7 jours

Middleware (filet de sécurité)
  EnsurePlanNotExpired → déclenche downgrade immédiat si expiré
      → redirige vers /upgrade avec message
```

---

## 3. Fichiers à créer

```
app/
├── Actions/
│   └── Subscription/
│       └── ActivateSubscriptionAction.php
├── Console/
│   └── Commands/
│       └── CheckSubscriptionsExpiration.php
├── Http/
│   ├── Controllers/
│   │   ├── AbonnementController.php        ← côté entreprise
│   │   └── Api/
│   │       └── PaymentWebhookController.php ← webhook placeholder
│   └── Middleware/
│       └── EnsurePlanNotExpired.php
├── Mail/
│   ├── SubscriptionExpiringSoon.php
│   └── SubscriptionExpired.php
└── Models/
    └── Subscription.php                    ← ajouter relation + scope

resources/js/Pages/
└── Abonnement/
    └── Index.vue                           ← page Mon abonnement
```

```
Modifier :
- app/Http/Controllers/Admin/PlanController.php   ← appeler ActivateSubscriptionAction
- app/Providers/AppServiceProvider.php            ← enregistrer le scheduler
- bootstrap/app.php ou Kernel.php                 ← enregistrer EnsurePlanNotExpired
- routes/web.php                                  ← route /abonnement
- routes/api.php                                  ← route webhook
- app/Http/Middleware/HandleInertiaRequests.php   ← partager plan_expires_at au frontend
```

---

## 4. ActivateSubscriptionAction

Point d'entrée unique pour toute activation de plan.

```php
// app/Actions/Subscription/ActivateSubscriptionAction.php

class ActivateSubscriptionAction
{
    public function execute(
        Entreprise $entreprise,
        string $plan,           // 'premium' | 'pro'
        int $durationMonths,    // 1–12, ou 0 si trial
        int $trialDays = 0,
        float $amount = 0,
        string $paymentMethod = 'manual',
        string $paymentReference = 'admin',
        ?int $confirmedBy = null,
    ): Subscription {
        $expiresAt = $trialDays > 0
            ? now()->addDays($trialDays)
            : now()->addMonths($durationMonths);

        $entreprise->update([
            'plan'            => $plan,
            'plan_expires_at' => $expiresAt,
        ]);

        $subscription = Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $plan,
            'amount'            => $amount,
            'payment_method'    => $paymentMethod,
            'payment_reference' => $paymentReference,
            'status'            => 'confirmed',
            'starts_at'         => now(),
            'expires_at'        => $expiresAt,
            'confirmed_by'      => $confirmedBy,
        ]);

        // Email au super_admin de l'entreprise
        $adminUser = $entreprise->users()
            ->where('role', 'super_admin')
            ->orderBy('id')
            ->first();

        if ($adminUser?->email) {
            Mail::to($adminUser->email)
                ->send(new SubscriptionConfirmed(
                    userName:       $adminUser->name,
                    entrepriseName: $entreprise->name,
                    plan:           $plan,
                    expireDate:     $expiresAt->format('d/m/Y'),
                    amount:         $amount,
                    isTrial:        $trialDays > 0,
                    trialDays:      $trialDays,
                    appUrl:         config('app.url'),
                ));
        }

        return $subscription;
    }
}
```

---

## 5. CheckSubscriptionsExpiration command

```bash
php artisan subscriptions:check
```

### Logique

**Passage 1 — Expirations**
```
SELECT * FROM entreprises
WHERE plan != 'free'
AND plan_expires_at < NOW()
```
Pour chaque résultat :
1. `entreprise->update(['plan' => 'free', 'plan_expires_at' => null])`
2. `Subscription::where('entreprise_id', ...)->where('status', 'confirmed')->update(['status' => 'expired'])`
3. Envoyer `SubscriptionExpired` mail au super_admin

**Passage 2 — Avertissements 7 jours**
```
SELECT * FROM entreprises
WHERE plan != 'free'
AND plan_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
AND plan_expires_at > NOW()
```
Pour chaque résultat : envoyer `SubscriptionExpiringSoon` mail.

### Scheduler (AppServiceProvider ou Console/Kernel)
```php
Schedule::command('subscriptions:check')->dailyAt('02:00');
```

---

## 6. EnsurePlanNotExpired middleware

Filet de sécurité en complément du cron. Déclenche le downgrade immédiatement si l'utilisateur se connecte avec un plan expiré (au lieu d'attendre la nuit suivante).

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if (! $user) return $next($request);

    $entreprise = $user->entreprise;
    if (! $entreprise) return $next($request);

    if (
        $entreprise->plan !== 'free'
        && $entreprise->plan_expires_at !== null
        && $entreprise->plan_expires_at->isPast()
    ) {
        // Downgrade immédiat
        $entreprise->update(['plan' => 'free', 'plan_expires_at' => null]);
        Subscription::where('entreprise_id', $entreprise->id)
            ->where('status', 'confirmed')
            ->update(['status' => 'expired']);
    }

    return $next($request);
}
```

Enregistré dans le groupe `web` middleware, **après** `auth`.

---

## 7. Page "Mon abonnement" (entreprise)

Route : `GET /abonnement` → `AbonnementController::index()`
Middleware : `auth`

### Données transmises à la Vue
```php
[
    'plan'            => $entreprise->plan,
    'plan_expires_at' => $entreprise->plan_expires_at?->toIso8601String(),
    'jours_restants'  => $entreprise->plan_expires_at
        ? max(0, now()->diffInDays($entreprise->plan_expires_at, false))
        : null,
    'historique'      => SubscriptionResource::collection(
        Subscription::where('entreprise_id', $entreprise->id)
            ->latest()->take(10)->get()
    ),
    'prices'          => ['premium' => 7, 'pro' => 10],
]
```

### Formulaire de demande
L'entreprise remplit :
- Plan voulu (premium / pro)
- Durée (1–12 mois)
- Méthode de paiement (texte libre : MTN MoMo, Airtel, virement...)
- Référence de paiement (numéro de transaction)

Soumission → `POST /abonnement/demande` → `AbonnementController::storeDemande()` → crée `Subscription` avec `status = 'pending'`. Owner voit la demande dans son dashboard et la confirme via `Admin/PlanController::activate()`.

---

## 8. Webhook placeholder

```php
// POST /api/v1/payment/webhook
// Header : X-Webhook-Signature: HMAC-SHA256(body, WEBHOOK_SECRET)

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Validation signature HMAC
        $signature = $request->header('X-Webhook-Signature', '');
        $expected  = hash_hmac('sha256', $request->getContent(), config('services.webhook_secret'));

        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->validate([
            'event'             => ['required', 'string'],
            'entreprise_id'     => ['required', 'integer'],
            'plan'              => ['required', 'in:premium,pro'],
            'duration_months'   => ['required', 'integer', 'min:1'],
            'amount'            => ['required', 'numeric'],
            'payment_reference' => ['required', 'string'],
        ]);

        if ($payload['event'] !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        $entreprise = Entreprise::findOrFail($payload['entreprise_id']);

        (new ActivateSubscriptionAction())->execute(
            entreprise:        $entreprise,
            plan:              $payload['plan'],
            durationMonths:    $payload['duration_months'],
            amount:            $payload['amount'],
            paymentMethod:     'webhook',
            paymentReference:  $payload['payment_reference'],
        );

        return response()->json(['status' => 'activated']);
    }
}
```

---

## 9. Emails

### SubscriptionExpiringSoon
- Destinataire : super_admin de l'entreprise
- Déclencheur : cron, 7 jours avant `plan_expires_at`
- Contenu : plan actuel, date d'expiration, lien `/abonnement` pour renouveler

### SubscriptionExpired
- Destinataire : super_admin de l'entreprise
- Déclencheur : cron, au moment du downgrade
- Contenu : plan downgradé vers free, historique perdu (données conservées), lien `/abonnement`

---

## 10. Ordre d'implémentation

```
Phase 1 — Core
  1.1  ActivateSubscriptionAction (+ refactorer PlanController pour l'utiliser)
  1.2  CheckSubscriptionsExpiration command + scheduler
  1.3  Mails SubscriptionExpiringSoon + SubscriptionExpired
  1.4  EnsurePlanNotExpired middleware

Phase 2 — Frontend entreprise
  2.1  AbonnementController (index + storeDemande)
  2.2  Route /abonnement + /abonnement/demande
  2.3  Page Vue Abonnement/Index.vue

Phase 3 — Webhook
  3.1  PaymentWebhookController (placeholder)
  3.2  Route /api/v1/payment/webhook
  3.3  WEBHOOK_SECRET dans .env.example
```

---

## 11. Contraintes

- `ActivateSubscriptionAction` est le seul endroit où `entreprise.plan` est modifié (sauf downgrade par le cron et le middleware)
- Le cron ne doit pas envoyer deux emails d'avertissement le même jour — ajouter un champ `warning_sent_at` sur `subscriptions` ou utiliser le Cache Laravel
- Le webhook vérifie toujours la signature HMAC avant de traiter — jamais de bypass
- Le plan `free` n'a pas de `plan_expires_at` — toujours `null`
