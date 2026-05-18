# Période d'Essai 2 Jours — Design

> **For agentic workers:** Implement via `superpowers:subagent-driven-development`.

**Goal:** Toute nouvelle entreprise démarre avec le plan Pro pendant 2 jours. Sans paiement, le plan retombe sur Free automatiquement. Deux emails sont envoyés : un rappel J-1, un à l'expiration.

**Architecture:** Modification de `EntrepriseController.store()` (activation trial), ajustement de `CheckSubscriptionsExpiration` (fenêtre warning J-1 pour trials), vérification du scheduling dans `Kernel.php`.

---

## 1. Activation du trial à l'inscription

**Fichier :** `app/Http/Controllers/EntrepriseController.php`

Dans la transaction `DB::transaction()`, après création de l'entreprise et du super_admin, ajouter :

```php
// Activer le plan Pro trial 2 jours
$entreprise->update([
    'plan' => 'pro',
    'plan_expires_at' => now()->addDays(2),
]);

Subscription::create([
    'entreprise_id' => $entreprise->id,
    'plan'          => 'pro',
    'amount'        => 0,
    'status'        => 'trial',
    'payment_method'    => 'trial',
    'payment_reference' => 'trial-2j',
    'starts_at'     => now(),
    'expires_at'    => now()->addDays(2),
]);
```

---

## 2. Emails d'expiration — ajustement `CheckSubscriptionsExpiration`

**Fichier :** `app/Console/Commands/CheckSubscriptionsExpiration.php`

### 2a. `downgradeExpired()` — inclure les trials

La méthode actuelle filtre `status = 'confirmed'` pour marquer comme `expired`. Ajouter les trials :

```php
Subscription::where('entreprise_id', $entreprise->id)
    ->whereIn('status', ['confirmed', 'trial'])
    ->update(['status' => 'expired']);
```

### 2b. `sendExpiringWarnings()` — fenêtre J-1 pour trials

**Problème actuel :** fenêtre de 7 jours → un trial de 2 jours déclenche le warning le jour même de la création.

**Fix :** distinguer trial et paid :

```php
private function sendExpiringWarnings(): void
{
    // Paid : avertir dans les 7 prochains jours
    $this->sendWarningsForGroup(
        statusFilter: ['confirmed'],
        windowDays: 7,
    );

    // Trial : avertir uniquement dans les 24h (J-1)
    $this->sendWarningsForGroup(
        statusFilter: ['trial'],
        windowDays: 1,
    );
}

private function sendWarningsForGroup(array $statusFilter, int $windowDays): void
{
    $entreprises = Entreprise::query()
        ->where('plan', '!=', 'free')
        ->whereNotNull('plan_expires_at')
        ->where('plan_expires_at', '>', now())
        ->where('plan_expires_at', '<=', now()->addDays($windowDays))
        ->whereHas('subscriptions', fn ($q) => $q->whereIn('status', $statusFilter))
        ->get();

    foreach ($entreprises as $entreprise) {
        // Éviter double envoi le même jour
        $alreadySent = $entreprise->subscriptions()
            ->whereIn('status', $statusFilter)
            ->whereNotNull('warning_sent_at')
            ->whereDate('warning_sent_at', today())
            ->exists();

        if ($alreadySent) continue;

        $joursRestants = (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false));

        $adminUser = $entreprise->users()
            ->where('role', 'super_admin')
            ->orderBy('id')->first()
            ?? $entreprise->users()->orderBy('id')->first();

        if ($adminUser?->email) {
            try {
                Mail::to($adminUser->email, $adminUser->name)
                    ->send(new SubscriptionExpiringSoon(
                        userName: $adminUser->name,
                        entrepriseName: $entreprise->name,
                        plan: $entreprise->plan,
                        expireDate: $entreprise->plan_expires_at->format('d/m/Y'),
                        joursRestants: $joursRestants,
                        appUrl: config('app.url'),
                    ));

                $entreprise->subscriptions()
                    ->whereIn('status', $statusFilter)
                    ->update(['warning_sent_at' => now()]);

                $this->info("Avertissement envoyé : {$entreprise->name} (expire dans {$joursRestants}j)");
            } catch (\Exception $e) {
                $this->warn("Email avertissement échoué pour {$entreprise->name} : {$e->getMessage()}");
            }
        }
    }
}
```

### 2c. Relation `subscriptions()` sur `Entreprise`

Vérifier que le modèle `Entreprise` a bien :
```php
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}
```

---

## 3. Scheduling

**Fichier :** `app/Console/Kernel.php`

Vérifier et s'assurer que la commande tourne quotidiennement :

```php
$schedule->command('subscriptions:check')->dailyAt('02:00');
```

---

## Ce qui NE change PAS

- `EnsurePlanNotExpired` middleware (déjà fonctionnel pour le downgrade en temps réel)
- `SubscriptionExpired` et `SubscriptionExpiringSoon` Mails (déjà implémentés)
- La logique de blocage des features (`CheckPlanLimit`)
- Le flux de paiement Netikash
