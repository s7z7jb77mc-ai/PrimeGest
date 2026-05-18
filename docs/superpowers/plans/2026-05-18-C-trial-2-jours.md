# Période d'Essai 2 Jours — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Toute nouvelle entreprise démarre en plan Pro pendant 2 jours. Sans paiement, retombe sur Free. Email J-1 de rappel, email à l'expiration.

**Architecture:** `EntrepriseController.store()` active le plan Pro trial à l'inscription. `CheckSubscriptionsExpiration` est ajusté pour envoyer le warning à J-1 uniquement pour les trials (au lieu de 7 jours). `Kernel.php` planifie la commande quotidiennement.

**Tech Stack:** Laravel 12, PHP 8.2, PHPUnit, Eloquent, Mail (Resend)

---

## Fichiers modifiés

- Modify: `app/Http/Controllers/EntrepriseController.php`
- Modify: `app/Console/Commands/CheckSubscriptionsExpiration.php`
- Modify: `app/Console/Kernel.php`
- Create: `tests/Feature/TrialActivationTest.php`
- Create: `tests/Feature/CheckSubscriptionsExpirationTest.php`

---

### Task 1 : Activer le plan Pro trial à l'inscription

**Files:**
- Modify: `app/Http/Controllers/EntrepriseController.php`
- Create: `tests/Feature/TrialActivationTest.php`

- [ ] **Step 1 : Écrire les tests**

Créer `tests/Feature/TrialActivationTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_entreprise_starts_on_pro_trial(): void
    {
        $response = $this->post('/register-entreprise', [
            'entreprise_name'    => 'Test Corp',
            'entreprise_email'   => 'corp@test.com',
            'entreprise_phone'   => '0990000000',
            'entreprise_address' => 'Lubumbashi',
            'admin_name'         => 'Admin Test',
            'admin_email'        => 'admin@test.com',
            'admin_password'     => 'password123',
            'admin_password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));

        $entreprise = Entreprise::where('email', 'corp@test.com')->firstOrFail();

        $this->assertSame('pro', $entreprise->plan);
        $this->assertNotNull($entreprise->plan_expires_at);
        $this->assertTrue($entreprise->plan_expires_at->isFuture());
        $this->assertEqualsWithDelta(2, now()->diffInDays($entreprise->plan_expires_at), 0.1);
    }

    public function test_new_entreprise_trial_creates_subscription_record(): void
    {
        $this->post('/register-entreprise', [
            'entreprise_name'    => 'Trial Corp',
            'entreprise_email'   => 'trial@corp.com',
            'entreprise_phone'   => '0990000001',
            'entreprise_address' => 'Kinshasa',
            'admin_name'         => 'Admin Trial',
            'admin_email'        => 'admin@trial.com',
            'admin_password'     => 'password123',
            'admin_password_confirmation' => 'password123',
        ]);

        $entreprise = Entreprise::where('email', 'trial@corp.com')->firstOrFail();

        $subscription = Subscription::where('entreprise_id', $entreprise->id)->firstOrFail();

        $this->assertSame('pro', $subscription->plan);
        $this->assertSame('trial', $subscription->status);
        $this->assertSame(0.0, (float) $subscription->amount);
        $this->assertSame('trial', $subscription->payment_method);
    }
}
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
php artisan test --filter TrialActivationTest
```

Expected: FAIL — l'entreprise a `plan = null` ou `plan = 'free'`, pas `pro`

- [ ] **Step 3 : Modifier `EntrepriseController.store()`**

Dans `app/Http/Controllers/EntrepriseController.php`, ajouter l'import au début du fichier :

```php
use App\Models\Subscription;
```

Dans la transaction `DB::transaction()`, après `$entreprise->update(['user_id' => $admin->id])`, ajouter :

```php
// Activer le plan Pro trial 2 jours
$expiresAt = now()->addDays(2);

$entreprise->update([
    'plan'            => 'pro',
    'plan_expires_at' => $expiresAt,
]);

Subscription::create([
    'entreprise_id'     => $entreprise->id,
    'plan'              => 'pro',
    'amount'            => 0,
    'status'            => 'trial',
    'payment_method'    => 'trial',
    'payment_reference' => 'trial-2j-'.now()->format('YmdHis'),
    'starts_at'         => now(),
    'expires_at'        => $expiresAt,
]);
```

- [ ] **Step 4 : Lancer les tests — vérifier qu'ils passent**

```bash
php artisan test --filter TrialActivationTest
```

Expected: PASS (2 tests)

- [ ] **Step 5 : Lancer la suite complète**

```bash
php artisan test
```

Expected: tous les tests passent

- [ ] **Step 6 : Commit**

```bash
git add app/Http/Controllers/EntrepriseController.php tests/Feature/TrialActivationTest.php
git commit -m "feat(inscription): activer plan Pro trial 2 jours à la création d'entreprise"
```

---

### Task 2 : Ajuster `CheckSubscriptionsExpiration` pour le warning J-1 trial

**Files:**
- Modify: `app/Console/Commands/CheckSubscriptionsExpiration.php`
- Create: `tests/Feature/CheckSubscriptionsExpirationTest.php`

- [ ] **Step 1 : Écrire les tests**

Créer `tests/Feature/CheckSubscriptionsExpirationTest.php` :

```php
<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiringSoon;
use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckSubscriptionsExpirationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntrepriseWithUser(string $plan, string $status, \Carbon\Carbon $expiresAt): Entreprise
    {
        $entreprise = Entreprise::factory()->create([
            'plan' => $plan,
            'plan_expires_at' => $expiresAt,
        ]);
        User::factory()->create([
            'role' => 'super_admin',
            'entreprise_id' => $entreprise->id,
            'email' => 'admin@' . $entreprise->id . '.com',
        ]);
        Subscription::factory()->create([
            'entreprise_id' => $entreprise->id,
            'plan'          => $plan,
            'status'        => $status,
            'expires_at'    => $expiresAt,
        ]);
        return $entreprise;
    }

    public function test_trial_warning_not_sent_on_day_of_creation(): void
    {
        Mail::fake();

        // Trial créé maintenant, expire dans 2 jours — ne doit PAS envoyer le warning
        $this->makeEntrepriseWithUser('pro', 'trial', now()->addDays(2));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertNotSent(SubscriptionExpiringSoon::class);
    }

    public function test_trial_warning_sent_one_day_before_expiry(): void
    {
        Mail::fake();

        // Trial expire dans 20 heures (J-1)
        $this->makeEntrepriseWithUser('pro', 'trial', now()->addHours(20));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertSent(SubscriptionExpiringSoon::class);
    }

    public function test_paid_warning_not_sent_two_days_before(): void
    {
        Mail::fake();

        // Abonnement payé expire dans 2 jours — fenêtre 7 jours, DOIT envoyer
        $this->makeEntrepriseWithUser('premium', 'confirmed', now()->addDays(2));

        $this->artisan('subscriptions:check')->assertExitCode(0);

        Mail::assertSent(SubscriptionExpiringSoon::class);
    }

    public function test_expired_trial_is_downgraded_to_free(): void
    {
        $entreprise = $this->makeEntrepriseWithUser('pro', 'trial', now()->subHour());

        $this->artisan('subscriptions:check')->assertExitCode(0);

        $entreprise->refresh();
        $this->assertSame('free', $entreprise->plan);
        $this->assertNull($entreprise->plan_expires_at);

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $entreprise->id,
            'status'        => 'expired',
        ]);
    }
}
```

- [ ] **Step 2 : Lancer les tests — vérifier qu'ils échouent**

```bash
php artisan test --filter CheckSubscriptionsExpirationTest
```

Expected: plusieurs FAIL — la logique de fenêtre actuelle (7 jours) envoie le warning dès la création du trial

- [ ] **Step 3 : Refactorer `CheckSubscriptionsExpiration`**

Remplacer le contenu de `app/Console/Commands/CheckSubscriptionsExpiration.php` par :

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SubscriptionExpired;
use App\Mail\SubscriptionExpiringSoon;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionsExpiration extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Downgrade les abonnements expirés et envoie les emails d\'avertissement';

    public function handle(): int
    {
        $this->downgradeExpired();
        $this->sendWarningsForGroup(statusFilter: ['confirmed'], windowDays: 7);
        $this->sendWarningsForGroup(statusFilter: ['trial'], windowDays: 1);

        return Command::SUCCESS;
    }

    private function downgradeExpired(): void
    {
        $expiredEntreprises = Entreprise::query()
            ->where('plan', '!=', 'free')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($expiredEntreprises as $entreprise) {
            $planAncien = $entreprise->plan;

            $entreprise->update([
                'plan'            => 'free',
                'plan_expires_at' => null,
            ]);

            Subscription::where('entreprise_id', $entreprise->id)
                ->whereIn('status', ['confirmed', 'trial'])
                ->update(['status' => 'expired']);

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first()
                ?? $entreprise->users()->orderBy('id')->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpired(
                            userName:       $adminUser->name,
                            entrepriseName: $entreprise->name,
                            planAncien:     $planAncien,
                            appUrl:         config('app.url'),
                        ));
                } catch (\Exception $e) {
                    $this->warn("Email expiration échoué pour {$entreprise->name} : {$e->getMessage()}");
                }
            }

            $this->info("Downgrade : {$entreprise->name} ({$planAncien} → free)");
        }
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
            $alreadySent = $entreprise->subscriptions()
                ->whereIn('status', $statusFilter)
                ->whereNotNull('warning_sent_at')
                ->whereDate('warning_sent_at', today())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $joursRestants = (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false));

            $adminUser = $entreprise->users()
                ->where('role', 'super_admin')
                ->orderBy('id')
                ->first()
                ?? $entreprise->users()->orderBy('id')->first();

            if ($adminUser?->email) {
                try {
                    Mail::to($adminUser->email, $adminUser->name)
                        ->send(new SubscriptionExpiringSoon(
                            userName:       $adminUser->name,
                            entrepriseName: $entreprise->name,
                            plan:           $entreprise->plan,
                            expireDate:     $entreprise->plan_expires_at->format('d/m/Y'),
                            joursRestants:  $joursRestants,
                            appUrl:         config('app.url'),
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
}
```

- [ ] **Step 4 : Lancer les tests — vérifier qu'ils passent**

```bash
php artisan test --filter CheckSubscriptionsExpirationTest
```

Expected: PASS (4 tests)

- [ ] **Step 5 : Lancer la suite complète**

```bash
php artisan test
```

Expected: tous les tests passent

- [ ] **Step 6 : Commit**

```bash
git add app/Console/Commands/CheckSubscriptionsExpiration.php tests/Feature/CheckSubscriptionsExpirationTest.php
git commit -m "feat(subscriptions): warning J-1 pour trials, J-7 pour paid — downgrade inclut trials"
```

---

### Task 3 : Planifier `subscriptions:check` dans Kernel.php

**Files:**
- Modify: `app/Console/Kernel.php`

- [ ] **Step 1 : Vérifier l'état actuel**

```bash
grep -n "subscriptions:check" app/Console/Kernel.php
```

Si la ligne n'existe pas, passer au Step 2. Si elle existe déjà, passer directement au Step 3.

- [ ] **Step 2 : Ajouter la planification dans `Kernel.php`**

Dans la méthode `schedule()`, ajouter après les commandes existantes :

```php
$schedule->command('subscriptions:check')->dailyAt('02:00');
```

- [ ] **Step 3 : Vérifier que la commande apparaît dans la liste**

```bash
php artisan schedule:list
```

Expected: `subscriptions:check` apparaît avec `Daily at 02:00`

- [ ] **Step 4 : Tester la commande manuellement**

```bash
php artisan subscriptions:check
```

Expected: `0 downgrade(s)` et `0 avertissement(s)` (ou messages correspondant à l'état actuel de la DB)

- [ ] **Step 5 : Commit**

```bash
git add app/Console/Kernel.php
git commit -m "feat(scheduler): planifier subscriptions:check quotidiennement à 02:00"
```
