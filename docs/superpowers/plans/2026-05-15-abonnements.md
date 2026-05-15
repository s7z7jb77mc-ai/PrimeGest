# Système d'abonnements — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compléter le système d'abonnements PrimeGest — activation via une Action unique, downgrade automatique quotidien, emails d'avertissement, middleware d'expiration, page entreprise, et webhook placeholder pour l'agrégateur de paiement futur.

**Architecture:** `ActivateSubscriptionAction` est le point d'entrée unique pour toute activation (manuel Owner + webhook futur). Un command Laravel tourne quotidiennement pour dégrader les plans expirés. `EnsurePlanNotExpired` middleware déclenche le downgrade immédiat si l'utilisateur se connecte avec un plan expiré.

**Tech Stack:** Laravel 12, Sanctum, Inertia.js, Vue 3, Resend (noreply@primegest.app), scheduler Laravel via `routes/console.php`

---

## Fichiers créés / modifiés

| Fichier | Rôle |
|---------|------|
| `app/Actions/Subscription/ActivateSubscriptionAction.php` | **CRÉER** — point d'entrée unique activation |
| `app/Http/Controllers/Admin/PlanController.php` | **MODIFIER** — déléguer à ActivateSubscriptionAction |
| `database/migrations/2026_05_15_000001_add_warning_sent_at_to_subscriptions.php` | **CRÉER** — colonne anti-doublon email avertissement |
| `app/Console/Commands/CheckSubscriptionsExpiration.php` | **CRÉER** — command quotidienne |
| `routes/console.php` | **MODIFIER** — enregistrer le scheduler |
| `app/Mail/SubscriptionExpiringSoon.php` | **CRÉER** — email 7j avant expiration |
| `app/Mail/SubscriptionExpired.php` | **CRÉER** — email au moment du downgrade |
| `resources/views/emails/subscription-expiring-soon.blade.php` | **CRÉER** — vue email avertissement |
| `resources/views/emails/subscription-expired.blade.php` | **CRÉER** — vue email expiration |
| `app/Http/Middleware/EnsurePlanNotExpired.php` | **CRÉER** — downgrade immédiat à la connexion |
| `bootstrap/app.php` | **MODIFIER** — enregistrer EnsurePlanNotExpired dans web middleware |
| `app/Http/Controllers/AbonnementController.php` | **CRÉER** — page et demande abonnement côté entreprise |
| `routes/web.php` | **MODIFIER** — ajouter routes /abonnement |
| `resources/js/Pages/Abonnement/Index.vue` | **CRÉER** — page Vue Mon abonnement |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | **CRÉER** — webhook placeholder |
| `routes/api.php` | **MODIFIER** — ajouter route webhook |
| `.env.example` | **MODIFIER** — ajouter WEBHOOK_SECRET |

---

## Task 1 — ActivateSubscriptionAction + refactoring PlanController

**Files:**
- Create: `app/Actions/Subscription/ActivateSubscriptionAction.php`
- Modify: `app/Http/Controllers/Admin/PlanController.php`

- [ ] **Step 1 — Créer le dossier et l'Action**

```php
<?php
// app/Actions/Subscription/ActivateSubscriptionAction.php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Mail\SubscriptionConfirmed;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ActivateSubscriptionAction
{
    public function execute(
        Entreprise $entreprise,
        string $plan,
        int $durationMonths,
        int $trialDays = 0,
        float $amount = 0.0,
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

        $adminUser = $entreprise->users()
            ->where('role', 'super_admin')
            ->orderBy('id')
            ->first()
            ?? $entreprise->users()->orderBy('id')->first();

        if ($adminUser?->email) {
            try {
                Mail::to($adminUser->email, $adminUser->name)
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
            } catch (\Exception $e) {
                Log::warning('Email abonnement échoué: ' . $e->getMessage());
            }
        }

        return $subscription;
    }
}
```

- [ ] **Step 2 — Refactorer PlanController::activate() pour déléguer à l'Action**

Ouvrir `app/Http/Controllers/Admin/PlanController.php`.

Remplacer la méthode `activate()` entière par :

```php
public function activate(Request $request, Entreprise $entreprise): \Illuminate\Http\RedirectResponse
{
    $request->validate([
        'plan'       => 'required|in:free,premium,pro',
        'duration'   => 'required|integer|min:1|max:12',
        'trial_days' => 'nullable|integer|min:0|max:30',
    ]);

    $trialDays = intval($request->trial_days ?? 0);
    $isTrial   = $trialDays > 0;
    $amount    = $isTrial ? 0 : ($request->plan === 'premium' ? 7 : 10);
    $method    = $isTrial ? 'trial' : ($request->payment_method ?? 'manual');
    $ref       = $isTrial ? "trial-{$trialDays}j" : ($request->payment_reference ?? 'admin');

    (new \App\Actions\Subscription\ActivateSubscriptionAction())->execute(
        entreprise:       $entreprise,
        plan:             $request->plan,
        durationMonths:   (int) $request->duration,
        trialDays:        $trialDays,
        amount:           $amount,
        paymentMethod:    $method,
        paymentReference: $ref,
        confirmedBy:      auth('owner')->id() ?? auth()->id(),
    );

    $msg = $isTrial
        ? "Essai {$trialDays} jours activé pour {$entreprise->name}"
        : "Plan {$request->plan} activé pour {$entreprise->name}";

    return back()->with('success', $msg);
}
```

- [ ] **Step 3 — Vérifier manuellement**

```bash
php artisan route:list | grep admin
```

Aller dans le dashboard Owner → activer un plan sur une entreprise test → vérifier que la colonne `plan` est mise à jour en DB et qu'une ligne `subscriptions` est créée avec `status = confirmed`.

- [ ] **Step 4 — Commit**

```bash
git add app/Actions/Subscription/ActivateSubscriptionAction.php \
        app/Http/Controllers/Admin/PlanController.php
git commit -m "feat: ActivateSubscriptionAction — point d'entrée unique activation plan"
```

---

## Task 2 — Migration warning_sent_at + Command CheckSubscriptionsExpiration

**Files:**
- Create: `database/migrations/2026_05_15_000001_add_warning_sent_at_to_subscriptions.php`
- Create: `app/Console/Commands/CheckSubscriptionsExpiration.php`
- Modify: `routes/console.php`

- [ ] **Step 1 — Créer la migration**

```php
<?php
// database/migrations/2026_05_15_000001_add_warning_sent_at_to_subscriptions.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('warning_sent_at')->nullable()->after('confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('warning_sent_at');
        });
    }
};
```

- [ ] **Step 2 — Lancer la migration**

```bash
php artisan migrate
```

Résultat attendu : `Migrating: 2026_05_15_000001_add_warning_sent_at_to_subscriptions` puis `Migrated`.

- [ ] **Step 3 — Créer la command**

```php
<?php
// app/Console/Commands/CheckSubscriptionsExpiration.php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionExpired;
use App\Mail\SubscriptionExpiringSoon;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionsExpiration extends Command
{
    protected $signature   = 'subscriptions:check';
    protected $description = 'Downgrade les plans expirés et envoie les emails d\'avertissement';

    public function handle(): void
    {
        $this->downgradeExpired();
        $this->sendWarnings();
    }

    private function downgradeExpired(): void
    {
        $entreprises = Entreprise::where('plan', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($entreprises as $entreprise) {
            $planAvant = $entreprise->plan;

            $entreprise->update([
                'plan'            => 'free',
                'plan_expires_at' => null,
            ]);

            Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'expired']);

            Log::info("subscriptions:check — downgrade {$entreprise->name} ({$planAvant} → free)");

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpired(
                            userName:       $adminUser->name,
                            entrepriseName: $entreprise->name,
                            planAvant:      $planAvant,
                            appUrl:         config('app.url'),
                        ));
                } catch (\Exception $e) {
                    Log::warning("Email expiration échoué pour {$entreprise->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Expirations traitées : {$entreprises->count()}");
    }

    private function sendWarnings(): void
    {
        $entreprises = Entreprise::where('plan', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->whereBetween('plan_expires_at', [now(), now()->addDays(7)])
            ->get();

        $sent = 0;

        foreach ($entreprises as $entreprise) {
            // Vérifier qu'on n'a pas déjà envoyé l'avertissement pour cet abonnement
            $subscription = Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->whereNull('warning_sent_at')
                ->latest()
                ->first();

            if (! $subscription) {
                continue;
            }

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpiringSoon(
                            userName:       $adminUser->name,
                            entrepriseName: $entreprise->name,
                            plan:           $entreprise->plan,
                            expireDate:     $entreprise->plan_expires_at->format('d/m/Y'),
                            joursRestants:  (int) now()->diffInDays($entreprise->plan_expires_at),
                            appUrl:         config('app.url'),
                        ));

                    $subscription->update(['warning_sent_at' => now()]);
                    $sent++;
                } catch (\Exception $e) {
                    Log::warning("Email avertissement échoué pour {$entreprise->name}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Avertissements envoyés : {$sent}");
    }
}
```

- [ ] **Step 4 — Enregistrer le scheduler dans routes/console.php**

Ouvrir `routes/console.php` et ajouter à la fin :

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:check')->dailyAt('02:00');
```

- [ ] **Step 5 — Tester la command manuellement**

```bash
php artisan subscriptions:check
```

Résultat attendu (si aucune expiration) :
```
Expirations traitées : 0
Avertissements envoyés : 0
```

- [ ] **Step 6 — Commit**

```bash
git add database/migrations/2026_05_15_000001_add_warning_sent_at_to_subscriptions.php \
        app/Console/Commands/CheckSubscriptionsExpiration.php \
        routes/console.php
git commit -m "feat: command subscriptions:check — downgrade auto + emails avertissement"
```

---

## Task 3 — Mails SubscriptionExpiringSoon + SubscriptionExpired

**Files:**
- Create: `app/Mail/SubscriptionExpiringSoon.php`
- Create: `app/Mail/SubscriptionExpired.php`
- Create: `resources/views/emails/subscription-expiring-soon.blade.php`
- Create: `resources/views/emails/subscription-expired.blade.php`

- [ ] **Step 1 — Créer SubscriptionExpiringSoon**

```php
<?php
// app/Mail/SubscriptionExpiringSoon.php

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
        public string $userName,
        public string $entrepriseName,
        public string $plan,
        public string $expireDate,
        public int    $joursRestants,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Votre abonnement PrimeGest expire dans {$this->joursRestants} jours",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expiring-soon',
        );
    }
}
```

- [ ] **Step 2 — Créer la vue subscription-expiring-soon.blade.php**

```blade
{{-- resources/views/emails/subscription-expiring-soon.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Abonnement bientôt expiré</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #1A56A0; padding: 20px; border-radius: 8px 8px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">PrimeGest</h1>
    </div>
    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 8px 8px;">
        <p>Bonjour <strong>{{ $userName }}</strong>,</p>
        <p>Votre abonnement <strong>{{ ucfirst($plan) }}</strong> pour l'entreprise <strong>{{ $entrepriseName }}</strong> expire dans <strong>{{ $joursRestants }} jour(s)</strong>, le <strong>{{ $expireDate }}</strong>.</p>
        <p>Après cette date, votre compte sera automatiquement rétrogradé vers le plan <strong>Free</strong> et vous perdrez l'accès aux fonctionnalités Premium.</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $appUrl }}/abonnement"
               style="background: #1A56A0; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                Renouveler mon abonnement
            </a>
        </div>
        <p style="color: #888; font-size: 13px;">L'équipe PrimeGest — noreply@primegest.app</p>
    </div>
</body>
</html>
```

- [ ] **Step 3 — Créer SubscriptionExpired**

```php
<?php
// app/Mail/SubscriptionExpired.php

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
        public string $userName,
        public string $entrepriseName,
        public string $planAvant,
        public string $appUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre abonnement PrimeGest a expiré — passage au plan Free',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-expired',
        );
    }
}
```

- [ ] **Step 4 — Créer la vue subscription-expired.blade.php**

```blade
{{-- resources/views/emails/subscription-expired.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Abonnement expiré</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #B91C1C; padding: 20px; border-radius: 8px 8px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">PrimeGest</h1>
    </div>
    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 8px 8px;">
        <p>Bonjour <strong>{{ $userName }}</strong>,</p>
        <p>Votre abonnement <strong>{{ ucfirst($planAvant) }}</strong> pour <strong>{{ $entrepriseName }}</strong> a expiré. Votre compte a été automatiquement rétrogradé vers le plan <strong>Free</strong>.</p>
        <p><strong>Vos données sont conservées.</strong> Vous pouvez renouveler votre abonnement à tout moment pour retrouver l'accès aux fonctionnalités Premium.</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $appUrl }}/abonnement"
               style="background: #1A56A0; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold;">
                Renouveler mon abonnement
            </a>
        </div>
        <p style="color: #888; font-size: 13px;">L'équipe PrimeGest — noreply@primegest.app</p>
    </div>
</body>
</html>
```

- [ ] **Step 5 — Commit**

```bash
git add app/Mail/SubscriptionExpiringSoon.php \
        app/Mail/SubscriptionExpired.php \
        resources/views/emails/subscription-expiring-soon.blade.php \
        resources/views/emails/subscription-expired.blade.php
git commit -m "feat: mails SubscriptionExpiringSoon + SubscriptionExpired"
```

---

## Task 4 — EnsurePlanNotExpired middleware

**Files:**
- Create: `app/Http/Middleware/EnsurePlanNotExpired.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1 — Créer le middleware**

```php
<?php
// app/Http/Middleware/EnsurePlanNotExpired.php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $entreprise = $user->entreprise;

        if (
            $entreprise
            && $entreprise->plan !== 'free'
            && $entreprise->plan_expires_at !== null
            && $entreprise->plan_expires_at->isPast()
        ) {
            $planAvant = $entreprise->plan;

            $entreprise->update([
                'plan'            => 'free',
                'plan_expires_at' => null,
            ]);

            Subscription::where('entreprise_id', $entreprise->id)
                ->where('status', 'confirmed')
                ->update(['status' => 'expired']);

            Log::info("EnsurePlanNotExpired — downgrade immédiat {$entreprise->name} ({$planAvant} → free)");
        }

        return $next($request);
    }
}
```

- [ ] **Step 2 — Enregistrer dans bootstrap/app.php**

Ouvrir `bootstrap/app.php`. Dans le bloc `->withMiddleware(function (Middleware $middleware) {`, ajouter `EnsurePlanNotExpired` dans le web stack **après** `HandleInertiaRequests` :

```php
$middleware->web(append: [
    \App\Http\Middleware\HandleInertiaRequests::class,
    \App\Http\Middleware\EnsureWritableAccess::class,
    \App\Http\Middleware\EnsurePlanNotExpired::class,   // ← ajouter ici
    \App\Http\Middleware\EnsurePageAccess::class,
]);
```

- [ ] **Step 3 — Vérifier**

```bash
php artisan route:list | head -5
```

Résultat attendu : pas d'erreur, les routes s'affichent normalement.

- [ ] **Step 4 — Commit**

```bash
git add app/Http/Middleware/EnsurePlanNotExpired.php bootstrap/app.php
git commit -m "feat: middleware EnsurePlanNotExpired — downgrade immédiat à la connexion"
```

---

## Task 5 — AbonnementController + routes

**Files:**
- Create: `app/Http/Controllers/AbonnementController.php`
- Modify: `routes/web.php`

- [ ] **Step 1 — Créer le controller**

```php
<?php
// app/Http/Controllers/AbonnementController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbonnementController extends Controller
{
    public function index(): Response
    {
        $user       = auth()->user();
        $entreprise = $user->entreprise;

        $joursRestants = $entreprise->plan_expires_at
            ? max(0, (int) now()->diffInDays($entreprise->plan_expires_at, false))
            : null;

        $historique = Subscription::where('entreprise_id', $entreprise->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'plan'           => $s->plan,
                'amount'         => $s->amount,
                'status'         => $s->status,
                'payment_method' => $s->payment_method,
                'starts_at'      => $s->starts_at?->format('d/m/Y'),
                'expires_at'     => $s->expires_at?->format('d/m/Y'),
                'created_at'     => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Abonnement/Index', [
            'plan'            => $entreprise->plan,
            'plan_expires_at' => $entreprise->plan_expires_at?->toIso8601String(),
            'jours_restants'  => $joursRestants,
            'historique'      => $historique,
            'prices'          => ['premium' => 7, 'pro' => 10],
        ]);
    }

    public function storeDemande(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan'               => 'required|in:premium,pro',
            'duration'           => 'required|integer|min:1|max:12',
            'payment_method'     => 'required|string|max:100',
            'payment_reference'  => 'required|string|max:255',
        ]);

        $entreprise = auth()->user()->entreprise;

        Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $validated['plan'],
            'amount'            => $validated['plan'] === 'premium' ? 7 * $validated['duration'] : 10 * $validated['duration'],
            'payment_method'    => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'],
            'status'            => 'pending',
            'starts_at'         => null,
            'expires_at'        => null,
        ]);

        return redirect()->route('abonnement.index')
            ->with('success', 'Demande envoyée. Notre équipe activera votre plan sous 24h.');
    }
}
```

- [ ] **Step 2 — Ajouter les routes dans routes/web.php**

Trouver le groupe `Route::middleware(['auth'])->group(function () {` dans `routes/web.php` et ajouter à l'intérieur :

```php
Route::get('/abonnement', [\App\Http\Controllers\AbonnementController::class, 'index'])
    ->name('abonnement.index');
Route::post('/abonnement/demande', [\App\Http\Controllers\AbonnementController::class, 'storeDemande'])
    ->name('abonnement.demande');
```

- [ ] **Step 3 — Vérifier les routes**

```bash
php artisan route:list | grep abonnement
```

Résultat attendu :
```
GET|HEAD   abonnement ................. abonnement.index
POST       abonnement/demande ......... abonnement.demande
```

- [ ] **Step 4 — Commit**

```bash
git add app/Http/Controllers/AbonnementController.php routes/web.php
git commit -m "feat: AbonnementController — page et demande abonnement côté entreprise"
```

---

## Task 6 — Page Vue Abonnement/Index.vue

**Files:**
- Create: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 — Créer la page**

```vue
<template>
  <AppLayout title="Mon abonnement">
    <div class="max-w-3xl mx-auto py-8 px-4">

      <!-- Statut actuel -->
      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-[#0B2D5E] mb-4">Abonnement actuel</h2>
        <div class="flex items-center gap-4">
          <span
            class="px-4 py-1 rounded-full text-sm font-bold uppercase"
            :class="{
              'bg-gray-200 text-gray-600': plan === 'free',
              'bg-blue-100 text-blue-700': plan === 'premium',
              'bg-purple-100 text-purple-700': plan === 'pro',
            }"
          >
            {{ plan }}
          </span>
          <span v-if="plan_expires_at" class="text-sm text-gray-500">
            Expire le {{ formatDate(plan_expires_at) }}
            <span
              v-if="jours_restants !== null && jours_restants <= 7"
              class="ml-2 text-orange-600 font-semibold"
            >
              ({{ jours_restants }}j restants)
            </span>
          </span>
          <span v-else-if="plan === 'free'" class="text-sm text-gray-400">Pas d'expiration</span>
        </div>
      </div>

      <!-- Tarifs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow p-6 border-2" :class="plan === 'premium' ? 'border-blue-500' : 'border-transparent'">
          <h3 class="font-bold text-[#1A56A0] text-lg mb-1">Premium</h3>
          <p class="text-2xl font-bold mb-2">{{ prices.premium }} $<span class="text-sm font-normal text-gray-500">/mois</span></p>
          <ul class="text-sm text-gray-600 space-y-1 mb-4">
            <li>✓ Utilisateurs illimités</li>
            <li>✓ Produits, clients, fournisseurs illimités</li>
            <li>✓ Export PDF / Excel</li>
            <li>✓ Dettes et créances</li>
          </ul>
        </div>
        <div class="bg-white rounded-xl shadow p-6 border-2" :class="plan === 'pro' ? 'border-purple-500' : 'border-transparent'">
          <h3 class="font-bold text-purple-700 text-lg mb-1">Pro</h3>
          <p class="text-2xl font-bold mb-2">{{ prices.pro }} $<span class="text-sm font-normal text-gray-500">/mois</span></p>
          <ul class="text-sm text-gray-600 space-y-1 mb-4">
            <li>✓ Tout Premium</li>
            <li>✓ Succursales illimitées</li>
            <li>✓ Transferts inter-succursales</li>
          </ul>
        </div>
      </div>

      <!-- Formulaire de demande -->
      <div class="bg-white rounded-xl shadow p-6 mb-8">
        <h2 class="text-lg font-semibold text-[#0B2D5E] mb-4">Soumettre une demande d'abonnement</h2>
        <p class="text-sm text-gray-500 mb-4">
          Effectuez votre paiement (MTN MoMo, Airtel, virement), puis remplissez ce formulaire.
          Notre équipe activera votre plan sous 24h.
        </p>
        <form @submit.prevent="submit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Plan souhaité</label>
              <select v-model="form.plan" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="premium">Premium — {{ prices.premium }}$/mois</option>
                <option value="pro">Pro — {{ prices.pro }}$/mois</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Durée (mois)</label>
              <select v-model="form.duration" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option v-for="n in 12" :key="n" :value="n">{{ n }} mois</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Méthode de paiement</label>
            <input v-model="form.payment_method" type="text" placeholder="Ex : MTN MoMo, Airtel Money, virement…"
              class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Référence / numéro de transaction</label>
            <input v-model="form.payment_reference" type="text" placeholder="Ex : TXN-1234567890"
              class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <p class="text-xs text-gray-400">
            Total estimé : <strong>{{ totalEstime }} $</strong> pour {{ form.duration }} mois
          </p>
          <button type="submit" :disabled="form.processing"
            class="w-full bg-[#1A56A0] text-white py-2 rounded-lg font-semibold hover:bg-[#0B2D5E] transition disabled:opacity-50">
            Envoyer la demande
          </button>
        </form>
      </div>

      <!-- Historique -->
      <div class="bg-white rounded-xl shadow p-6" v-if="historique.length">
        <h2 class="text-lg font-semibold text-[#0B2D5E] mb-4">Historique des abonnements</h2>
        <table class="w-full text-sm">
          <thead><tr class="text-left text-gray-500 border-b">
            <th class="pb-2">Plan</th><th class="pb-2">Montant</th><th class="pb-2">Statut</th>
            <th class="pb-2">Début</th><th class="pb-2">Fin</th>
          </tr></thead>
          <tbody>
            <tr v-for="s in historique" :key="s.id" class="border-b last:border-0">
              <td class="py-2 capitalize">{{ s.plan }}</td>
              <td class="py-2">{{ s.amount }} $</td>
              <td class="py-2">
                <span :class="{
                  'text-green-600': s.status === 'confirmed',
                  'text-yellow-600': s.status === 'pending',
                  'text-gray-400': s.status === 'expired',
                }">{{ s.status }}</span>
              </td>
              <td class="py-2">{{ s.starts_at ?? '—' }}</td>
              <td class="py-2">{{ s.expires_at ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppDashboardLayout.vue'

const props = defineProps<{
  plan: string
  plan_expires_at: string | null
  jours_restants: number | null
  historique: Array<{
    id: number
    plan: string
    amount: number
    status: string
    payment_method: string
    starts_at: string | null
    expires_at: string | null
    created_at: string
  }>
  prices: { premium: number; pro: number }
}>()

const form = useForm({
  plan: 'premium',
  duration: 1,
  payment_method: '',
  payment_reference: '',
})

const totalEstime = computed(() =>
  props.prices[form.plan as 'premium' | 'pro'] * form.duration
)

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR')
}

function submit(): void {
  form.post(route('abonnement.demande'))
}
</script>
```

- [ ] **Step 2 — Vérifier dans le navigateur**

```bash
php artisan serve
npm run dev
```

Naviguer vers `/abonnement` — la page doit afficher le plan actuel, les tarifs, le formulaire de demande et l'historique.

- [ ] **Step 3 — Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "feat: page Abonnement/Index.vue — vue plan + formulaire demande + historique"
```

---

## Task 7 — PaymentWebhookController (placeholder)

**Files:**
- Create: `app/Http/Controllers/Api/PaymentWebhookController.php`
- Modify: `routes/api.php`
- Modify: `.env.example`

- [ ] **Step 1 — Créer le controller**

```php
<?php
// app/Http/Controllers/Api/PaymentWebhookController.php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\ActivateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature', '');
        $expected  = hash_hmac('sha256', $request->getContent(), config('services.webhook_secret', ''));

        if (! hash_equals($expected, $signature)) {
            Log::warning('PaymentWebhook: signature invalide', ['ip' => $request->ip()]);
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

        $entreprise = Entreprise::find($payload['entreprise_id']);

        if (! $entreprise) {
            return response()->json(['error' => 'Entreprise not found'], 404);
        }

        (new ActivateSubscriptionAction())->execute(
            entreprise:       $entreprise,
            plan:             $payload['plan'],
            durationMonths:   $payload['duration_months'],
            amount:           $payload['amount'],
            paymentMethod:    'webhook',
            paymentReference: $payload['payment_reference'],
        );

        Log::info('PaymentWebhook: plan activé', [
            'entreprise_id' => $entreprise->id,
            'plan'          => $payload['plan'],
        ]);

        return response()->json(['status' => 'activated']);
    }
}
```

- [ ] **Step 2 — Ajouter la route dans routes/api.php**

Ajouter après la route `/health` :

```php
// Webhook paiement — pas d'auth, signature HMAC obligatoire
Route::post('/v1/payment/webhook', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:30,1');
```

- [ ] **Step 3 — Ajouter WEBHOOK_SECRET dans .env.example**

Trouver le bloc `APP_*` dans `.env.example` et ajouter :

```env
WEBHOOK_SECRET=your-webhook-secret-here
```

Ajouter aussi dans `config/services.php` à la fin du tableau :

```php
'webhook_secret' => env('WEBHOOK_SECRET', ''),
```

- [ ] **Step 4 — Vérifier la route**

```bash
php artisan route:list | grep webhook
```

Résultat attendu :
```
POST   api/v1/payment/webhook
```

- [ ] **Step 5 — Commit**

```bash
git add app/Http/Controllers/Api/PaymentWebhookController.php \
        routes/api.php \
        .env.example \
        config/services.php
git commit -m "feat: PaymentWebhookController — endpoint placeholder pour agrégateur de paiement"
```

---

## Vérification finale

```bash
# Toutes les routes abonnement
php artisan route:list | grep -E "abonnement|webhook|subscriptions"

# Tester la command
php artisan subscriptions:check

# Vérifier le scheduler (dry-run)
php artisan schedule:list
```

Résultat attendu de `schedule:list` :
```
0 2 * * *  php artisan subscriptions:check
```
