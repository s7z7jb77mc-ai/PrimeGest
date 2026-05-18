# Netikash Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Intégrer Netikash (OAuth2 + USSD Mobile Money) pour activer les abonnements Premium/Pro automatiquement sans validation manuelle.

**Architecture:** `PricingService` calcule les montants (avec réductions 6/12 mois). `NetikashService` gère le cycle OAuth2 (token Redis) et l'initiation du paiement. `AbonnementController` expose deux endpoints : initiation (web) et statut (API/polling). Le webhook Netikash active la subscription via `ActivateSubscriptionAction` existant. Le frontend remplace le formulaire manuel par un formulaire téléphone + polling 5s.

**Tech Stack:** Laravel 12 / PHP 8.2, Laravel HTTP Client, Redis (cache token), Vue 3 Composition API, Axios

---

## Fichiers touchés

| Fichier | Action | Rôle |
|---|---|---|
| `config/plans.php` | Modifier | Ajouter grille tarifaire promotionnelle |
| `config/services.php` | Modifier | Bloc config Netikash |
| `.env.example` | Modifier | Variables Netikash |
| `database/migrations/TIMESTAMP_add_failed_to_subscriptions_status.php` | Créer | ENUM status += 'failed' |
| `app/Services/PricingService.php` | Créer | Calcul montant avec réductions |
| `app/Services/NetikashService.php` | Créer | OAuth2 token + initiation paiement |
| `app/Http/Controllers/AbonnementController.php` | Modifier | +`initierPaiement()` +`statut()` +taux dans `index()` |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | Modifier | Adapter format Netikash |
| `routes/web.php` | Modifier | POST /abonnement/payer |
| `routes/api.php` | Modifier | GET /api/abonnement/statut/{reference} |
| `resources/js/Pages/Abonnement/Index.vue` | Modifier | Formulaire Netikash + polling |
| `tests/Feature/PricingServiceTest.php` | Créer | Tests grille tarifaire |
| `tests/Feature/NetikashServiceTest.php` | Créer | Tests service (Http mock) |
| `tests/Feature/AbonnementPaiementTest.php` | Créer | Tests feature initiation + statut |
| `tests/Feature/PaymentWebhookNetikashTest.php` | Créer | Tests webhook Netikash |

---

## Task 1 : Config et migration status 'failed'

**Files:**
- Modify: `config/plans.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `database/migrations/2026_05_18_000001_add_failed_to_subscriptions_status.php`

- [ ] **Step 1 : Ajouter la grille promotionnelle dans `config/plans.php`**

Ajouter après la clé `'prices'` existante :

```php
'promotional_prices' => [
    'premium' => [6 => 40.0, 12 => 70.0],
    'pro'     => [6 => 55.0, 12 => 100.0],
],
```

Résultat final de la clé `'prices'` + `'promotional_prices'` dans `config/plans.php` :

```php
'prices' => [
    'premium' => 7.0,
    'pro'     => 10.0,
],

'promotional_prices' => [
    'premium' => [6 => 40.0, 12 => 70.0],
    'pro'     => [6 => 55.0, 12 => 100.0],
],
```

- [ ] **Step 2 : Ajouter le bloc Netikash dans `config/services.php`**

Ajouter avant le `];` final :

```php
'netikash' => [
    'client_id'       => env('NETIKASH_CLIENT_ID'),
    'client_secret'   => env('NETIKASH_CLIENT_SECRET'),
    'base_url'        => env('NETIKASH_BASE_URL', 'https://gateway.netikash.com'),
    'token_path'      => env('NETIKASH_TOKEN_PATH', '/oauth/token'),
    'payment_path'    => env('NETIKASH_PAYMENT_PATH', '/api/v1/payment/initiate'),
    'webhook_secret'  => env('NETIKASH_WEBHOOK_SECRET'),
    'usd_to_cdf_rate' => (float) env('NETIKASH_USD_TO_CDF_RATE', 2800),
],
```

- [ ] **Step 3 : Ajouter les variables dans `.env.example`**

```dotenv
NETIKASH_CLIENT_ID=
NETIKASH_CLIENT_SECRET=
NETIKASH_BASE_URL=https://gateway.netikash.com
NETIKASH_TOKEN_PATH=/oauth/token
NETIKASH_PAYMENT_PATH=/api/v1/payment/initiate
NETIKASH_WEBHOOK_SECRET=
NETIKASH_USD_TO_CDF_RATE=2800
```

Copier aussi dans `.env` local avec tes vraies valeurs.

- [ ] **Step 4 : Créer la migration pour ajouter 'failed' à l'enum status**

```bash
php artisan make:migration add_failed_to_subscriptions_status
```

Contenu du fichier généré :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending','confirmed','expired') NOT NULL DEFAULT 'pending'");
    }
};
```

- [ ] **Step 5 : Lancer la migration**

```bash
php artisan migrate
```

Résultat attendu : `Migrating: ...add_failed_to_subscriptions_status` puis `Migrated`.

- [ ] **Step 6 : Commit**

```bash
git add config/plans.php config/services.php .env.example database/migrations/
git commit -m "feat(netikash): config services, grille promotionnelle, enum status failed"
```

---

## Task 2 : PricingService

**Files:**
- Create: `app/Services/PricingService.php`
- Create: `tests/Feature/PricingServiceTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/PricingServiceTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService();
    }

    public function test_premium_1_mois_usd(): void
    {
        $this->assertSame(7.0, $this->pricing->calculate('premium', 1, 'USD'));
    }

    public function test_premium_5_mois_usd(): void
    {
        $this->assertSame(35.0, $this->pricing->calculate('premium', 5, 'USD'));
    }

    public function test_premium_6_mois_usd_reduction(): void
    {
        $this->assertSame(40.0, $this->pricing->calculate('premium', 6, 'USD'));
    }

    public function test_premium_12_mois_usd_reduction(): void
    {
        $this->assertSame(70.0, $this->pricing->calculate('premium', 12, 'USD'));
    }

    public function test_pro_6_mois_usd_reduction(): void
    {
        $this->assertSame(55.0, $this->pricing->calculate('pro', 6, 'USD'));
    }

    public function test_pro_12_mois_usd_reduction(): void
    {
        $this->assertSame(100.0, $this->pricing->calculate('pro', 12, 'USD'));
    }

    public function test_premium_1_mois_cdf(): void
    {
        // 7 USD × taux config (2800 par défaut)
        $this->assertSame(7.0 * config('services.netikash.usd_to_cdf_rate'), $this->pricing->calculate('premium', 1, 'CDF'));
    }

    public function test_premium_6_mois_cdf_reduction(): void
    {
        $this->assertSame(40.0 * config('services.netikash.usd_to_cdf_rate'), $this->pricing->calculate('premium', 6, 'CDF'));
    }

    public function test_plan_invalide_leve_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->pricing->calculate('free', 1, 'USD');
    }

    public function test_devise_invalide_leve_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->pricing->calculate('premium', 1, 'EUR');
    }
}
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
php artisan test --filter PricingServiceTest
```

Résultat attendu : FAIL — `Class "App\Services\PricingService" not found`

- [ ] **Step 3 : Implémenter PricingService**

Créer `app/Services/PricingService.php` :

```php
<?php

declare(strict_types=1);

namespace App\Services;

class PricingService
{
    public function calculate(string $plan, int $duree, string $devise): float
    {
        if (! in_array($plan, ['premium', 'pro'], true)) {
            throw new \InvalidArgumentException("Plan '{$plan}' non supporté pour le paiement.");
        }

        if (! in_array($devise, ['USD', 'CDF'], true)) {
            throw new \InvalidArgumentException("Devise '{$devise}' non supportée. Utiliser USD ou CDF.");
        }

        $promo = config("plans.promotional_prices.{$plan}", []);
        $basePrice = (float) config("plans.prices.{$plan}", 0);

        $montantUsd = isset($promo[$duree])
            ? (float) $promo[$duree]
            : $basePrice * $duree;

        if ($devise === 'CDF') {
            $taux = (float) config('services.netikash.usd_to_cdf_rate', 2800);
            return $montantUsd * $taux;
        }

        return $montantUsd;
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
php artisan test --filter PricingServiceTest
```

Résultat attendu : 10 tests, 10 passed.

- [ ] **Step 5 : Commit**

```bash
git add app/Services/PricingService.php tests/Feature/PricingServiceTest.php
git commit -m "feat(netikash): PricingService avec réductions 6/12 mois"
```

---

## Task 3 : NetikashService

**Files:**
- Create: `app/Services/NetikashService.php`
- Create: `tests/Feature/NetikashServiceTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/NetikashServiceTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NetikashService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetikashServiceTest extends TestCase
{
    private function fakeTokenResponse(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'fake-token-abc123',
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ], 200),
        ]);
    }

    public function test_get_access_token_appelle_oauth_endpoint(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_access_token');

        $service = new NetikashService();
        $token = $service->getAccessToken();

        $this->assertSame('fake-token-abc123', $token);

        Http::assertSent(function (Request $req) {
            return str_contains($req->url(), '/oauth/token')
                && $req->hasHeader('Authorization');
        });
    }

    public function test_token_est_mis_en_cache(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_access_token');

        $service = new NetikashService();
        $service->getAccessToken();
        $service->getAccessToken(); // 2e appel

        Http::assertSentCount(1); // une seule requête HTTP
    }

    public function test_initiate_payment_envoie_bonne_requete(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_access_token');

        Http::fake([
            '*/oauth/token'        => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            '*/payment/initiate'   => Http::response([
                'transaction_id' => 'TXN-123',
                'status'         => 'pending',
                'message'        => 'USSD envoyé',
            ], 200),
        ]);

        $service = new NetikashService();
        $result = $service->initiatePayment(
            phone: '243812345678',
            amount: 7.0,
            currency: 'USD',
            reference: 'PG-1-ABC12345',
            description: 'Abonnement Premium 1 mois',
        );

        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertSame('TXN-123', $result['transaction_id']);
    }

    public function test_normalise_phone_avec_plus(): void
    {
        $service = new NetikashService();
        $this->assertSame('243812345678', $service->normalizePhone('+243812345678'));
    }

    public function test_normalise_phone_avec_00(): void
    {
        $service = new NetikashService();
        $this->assertSame('243812345678', $service->normalizePhone('00243812345678'));
    }

    public function test_normalise_phone_avec_espaces(): void
    {
        $service = new NetikashService();
        $this->assertSame('243812345678', $service->normalizePhone(' 243 812 345 678 '));
    }
}
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
php artisan test --filter NetikashServiceTest
```

Résultat attendu : FAIL — `Class "App\Services\NetikashService" not found`

- [ ] **Step 3 : Implémenter NetikashService**

Créer `app/Services/NetikashService.php` :

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetikashService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $tokenPath;
    private string $paymentPath;

    public function __construct()
    {
        $this->baseUrl       = (string) config('services.netikash.base_url', 'https://gateway.netikash.com');
        $this->clientId      = (string) config('services.netikash.client_id', '');
        $this->clientSecret  = (string) config('services.netikash.client_secret', '');
        $this->tokenPath     = (string) config('services.netikash.token_path', '/oauth/token');
        $this->paymentPath   = (string) config('services.netikash.payment_path', '/api/v1/payment/initiate');
    }

    public function getAccessToken(): string
    {
        return Cache::remember('netikash_access_token', 3540, function (): string {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . $this->tokenPath, [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                Log::error('Netikash: échec token OAuth2', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException('Netikash: impossible d\'obtenir le token d\'accès.');
            }

            $expiresIn = (int) ($response->json('expires_in') ?? 3600);
            // Re-mémoriser avec le vrai TTL moins 60s de marge
            Cache::put('netikash_access_token', $response->json('access_token'), max(60, $expiresIn - 60));

            return (string) $response->json('access_token');
        });
    }

    public function initiatePayment(
        string $phone,
        float  $amount,
        string $currency,
        string $reference,
        string $description,
    ): array {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($this->baseUrl . $this->paymentPath, [
                'phone'        => $this->normalizePhone($phone),
                'amount'       => $amount,
                'currency'     => $currency,
                'reference'    => $reference,
                'description'  => $description,
                'callback_url' => url('/api/v1/payment/webhook'),
            ]);

        if (! $response->successful()) {
            Log::error('Netikash: échec initiation paiement', [
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: échec de l\'initiation du paiement — ' . $response->body());
        }

        return $response->json() ?? [];
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', trim($phone));

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        return $phone;
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
php artisan test --filter NetikashServiceTest
```

Résultat attendu : 6 tests, 6 passed.

- [ ] **Step 5 : Commit**

```bash
git add app/Services/NetikashService.php tests/Feature/NetikashServiceTest.php
git commit -m "feat(netikash): NetikashService OAuth2 + initiation paiement"
```

---

## Task 4 : AbonnementController — initierPaiement + statut + mise à jour index

**Files:**
- Modify: `app/Http/Controllers/AbonnementController.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/AbonnementPaiementTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/AbonnementPaiementTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NetikashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AbonnementPaiementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entreprise = Entreprise::factory()->create(['plan' => 'free']);
        $this->user = User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'role'          => 'super_admin',
        ]);
    }

    public function test_initier_paiement_cree_subscription_pending(): void
    {
        $this->mock(NetikashService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')->once()->andReturn([
                'transaction_id' => 'TXN-TEST-001',
                'status'         => 'pending',
            ]);
        });

        $response = $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan'   => 'premium',
                'duree'  => 1,
                'phone'  => '243812345678',
                'devise' => 'USD',
            ]);

        $response->assertOk()
                 ->assertJsonPath('message', fn ($v) => str_contains($v, 'téléphone'))
                 ->assertJsonStructure(['reference', 'message']);

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $this->entreprise->id,
            'plan'          => 'premium',
            'amount'        => 7.0,
            'status'        => 'pending',
        ]);
    }

    public function test_initier_paiement_6_mois_applique_reduction(): void
    {
        $this->mock(NetikashService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')->once()->andReturn(['transaction_id' => 'TXN-TEST-002']);
        });

        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan'   => 'premium',
                'duree'  => 6,
                'phone'  => '243812345678',
                'devise' => 'USD',
            ])
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $this->entreprise->id,
            'amount'        => 40.0,
        ]);
    }

    public function test_initier_paiement_netikash_echoue_marque_failed(): void
    {
        $this->mock(NetikashService::class, function ($mock) {
            $mock->shouldReceive('initiatePayment')->once()
                 ->andThrow(new \RuntimeException('Netikash: service indisponible'));
        });

        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan'   => 'pro',
                'duree'  => 1,
                'phone'  => '243812345678',
                'devise' => 'USD',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('subscriptions', [
            'entreprise_id' => $this->entreprise->id,
            'status'        => 'failed',
        ]);
    }

    public function test_statut_retourne_statut_subscription(): void
    {
        $sub = Subscription::create([
            'entreprise_id'     => $this->entreprise->id,
            'plan'              => 'premium',
            'amount'            => 7.0,
            'payment_method'    => 'netikash',
            'payment_reference' => 'PG-1-TEST001',
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonth(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/abonnement/statut/PG-1-TEST001')
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('plan', 'premium');
    }

    public function test_statut_subscription_autre_entreprise_retourne_404(): void
    {
        $autreEntreprise = Entreprise::factory()->create();
        Subscription::create([
            'entreprise_id'     => $autreEntreprise->id,
            'plan'              => 'premium',
            'amount'            => 7.0,
            'payment_method'    => 'netikash',
            'payment_reference' => 'PG-99-OTHER',
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonth(),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/abonnement/statut/PG-99-OTHER')
            ->assertNotFound();
    }

    public function test_plan_invalide_est_rejete(): void
    {
        $this->actingAs($this->user)
            ->postJson('/abonnement/payer', [
                'plan'   => 'free',
                'duree'  => 1,
                'phone'  => '243812345678',
                'devise' => 'USD',
            ])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
php artisan test --filter AbonnementPaiementTest
```

Résultat attendu : FAIL — routes 404 ou méthodes inexistantes.

- [ ] **Step 3 : Mettre à jour AbonnementController**

Remplacer le contenu complet de `app/Http/Controllers/AbonnementController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\NetikashService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): Response
    {
        $entreprise = $request->user()->entreprise;

        $joursRestants = $entreprise->plan_expires_at
            ? (int) max(0, now()->diffInDays($entreprise->plan_expires_at, false))
            : null;

        $historique = Subscription::where('entreprise_id', $entreprise->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (Subscription $s) => [
                'id'                => $s->id,
                'plan'              => $s->plan,
                'amount'            => $s->amount,
                'status'            => $s->status,
                'payment_method'    => $s->payment_method,
                'payment_reference' => $s->payment_reference,
                'starts_at'         => $s->starts_at?->format('d/m/Y'),
                'expires_at'        => $s->expires_at?->format('d/m/Y'),
                'created_at'        => $s->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Abonnement/Index', [
            'plan'             => $entreprise->plan,
            'plan_expires_at'  => $entreprise->plan_expires_at?->toIso8601String(),
            'jours_restants'   => $joursRestants,
            'historique'       => $historique,
            'prices'           => config('plans.prices', ['premium' => 7, 'pro' => 10]),
            'promo_prices'     => config('plans.promotional_prices', []),
            'usd_to_cdf_rate'  => config('services.netikash.usd_to_cdf_rate', 2800),
        ]);
    }

    public function initierPaiement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan'   => ['required', 'in:premium,pro'],
            'duree'  => ['required', 'integer', 'min:1', 'max:12'],
            'phone'  => ['required', 'string', 'min:9', 'max:20'],
            'devise' => ['required', 'in:USD,CDF'],
        ]);

        $entreprise = $request->user()->entreprise;

        $pricing   = new PricingService();
        $montant   = $pricing->calculate($data['plan'], (int) $data['duree'], $data['devise']);
        $reference = 'PG-' . $entreprise->id . '-' . strtoupper(Str::random(8));
        $expiresAt = now()->addMonths((int) $data['duree']);

        $subscription = Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $data['plan'],
            'amount'            => $montant,
            'payment_method'    => 'netikash',
            'payment_reference' => $reference,
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => $expiresAt,
        ]);

        try {
            $netikash = app(NetikashService::class);
            $netikash->initiatePayment(
                phone:       $data['phone'],
                amount:      $montant,
                currency:    $data['devise'],
                reference:   $reference,
                description: 'Abonnement PrimeGest ' . ucfirst($data['plan']) . ' ' . $data['duree'] . ' mois',
            );
        } catch (\Throwable $e) {
            $subscription->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Le paiement n\'a pas pu être initié. Vérifiez votre numéro et réessayez.',
            ], 422);
        }

        return response()->json([
            'success'   => true,
            'reference' => $reference,
            'message'   => 'Confirmez le paiement sur votre téléphone.',
        ]);
    }

    public function statut(Request $request, string $reference): JsonResponse
    {
        $entreprise   = $request->user()->entreprise;
        $subscription = Subscription::where('payment_reference', $reference)
            ->where('entreprise_id', $entreprise->id)
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable.'], 404);
        }

        return response()->json([
            'status'     => $subscription->status,
            'plan'       => $subscription->plan,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
        ]);
    }
}
```

- [ ] **Step 4 : Ajouter les routes**

Dans `routes/web.php`, après la route `/abonnement/demande` existante, ajouter :

```php
Route::post('/abonnement/payer', [\App\Http\Controllers\AbonnementController::class, 'initierPaiement'])->name('abonnement.payer');
```

Dans `routes/api.php`, après le bloc des routes auth, ajouter :

```php
Route::middleware('auth:sanctum')
    ->get('/abonnement/statut/{reference}', [\App\Http\Controllers\AbonnementController::class, 'statut'])
    ->name('abonnement.statut');
```

- [ ] **Step 5 : Vérifier que les tests passent**

```bash
php artisan test --filter AbonnementPaiementTest
```

Résultat attendu : 6 tests, 6 passed.

- [ ] **Step 6 : Commit**

```bash
git add app/Http/Controllers/AbonnementController.php routes/web.php routes/api.php tests/Feature/AbonnementPaiementTest.php
git commit -m "feat(netikash): AbonnementController initierPaiement + statut + routes"
```

---

## Task 5 : PaymentWebhookController — adapter pour Netikash

**Files:**
- Modify: `app/Http/Controllers/Api/PaymentWebhookController.php`
- Create: `tests/Feature/PaymentWebhookNetikashTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/PaymentWebhookNetikashTest.php` :

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entreprise;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookNetikashTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Subscription $subscription;
    private string $secret = 'test-webhook-secret-32chars-ok!!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.netikash.webhook_secret' => $this->secret]);

        $this->entreprise = Entreprise::factory()->create(['plan' => 'free']);
        User::factory()->create([
            'entreprise_id' => $this->entreprise->id,
            'role'          => 'super_admin',
            'email'         => 'admin@test.com',
        ]);

        $this->subscription = Subscription::create([
            'entreprise_id'     => $this->entreprise->id,
            'plan'              => 'premium',
            'amount'            => 7.0,
            'payment_method'    => 'netikash',
            'payment_reference' => 'PG-1-WEBHTEST',
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonth(),
        ]);
    }

    private function buildPayload(array $overrides = []): array
    {
        return array_merge([
            'event'          => 'payment.success',
            'reference'      => 'PG-1-WEBHTEST',
            'transaction_id' => 'TXN-NETIKASH-999',
            'amount'         => 7.0,
            'currency'       => 'USD',
            'phone'          => '243812345678',
            'status'         => 'success',
        ], $overrides);
    }

    private function signedPost(array $payload): \Illuminate\Testing\TestResponse
    {
        $body      = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $this->secret);

        return $this->postJson(
            '/api/v1/payment/webhook',
            $payload,
            ['X-Netikash-Signature' => $signature],
        );
    }

    public function test_webhook_valide_active_subscription(): void
    {
        $this->signedPost($this->buildPayload())
             ->assertOk()
             ->assertJsonPath('status', 'activated');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscription->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('entreprises', [
            'id'   => $this->entreprise->id,
            'plan' => 'premium',
        ]);
    }

    public function test_webhook_signature_invalide_retourne_401(): void
    {
        $payload = $this->buildPayload();

        $this->postJson(
            '/api/v1/payment/webhook',
            $payload,
            ['X-Netikash-Signature' => 'mauvaise-signature'],
        )->assertStatus(401);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscription->id,
            'status' => 'pending',
        ]);
    }

    public function test_webhook_deja_confirme_retourne_200_sans_doublon(): void
    {
        $this->subscription->update(['status' => 'confirmed']);

        $this->signedPost($this->buildPayload())
             ->assertOk()
             ->assertJsonPath('status', 'already_confirmed');
    }

    public function test_webhook_reference_inconnue_retourne_404(): void
    {
        $this->signedPost($this->buildPayload(['reference' => 'PG-99-UNKNOWN']))
             ->assertNotFound();
    }

    public function test_webhook_event_non_payment_success_est_ignore(): void
    {
        $this->signedPost($this->buildPayload(['event' => 'payment.failed']))
             ->assertOk()
             ->assertJsonPath('status', 'ignored');

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $this->subscription->id,
            'status' => 'pending',
        ]);
    }
}
```

- [ ] **Step 2 : Vérifier que le test échoue**

```bash
php artisan test --filter PaymentWebhookNetikashTest
```

Résultat attendu : FAIL — signature ou logique actuelle différente.

- [ ] **Step 3 : Réécrire PaymentWebhookController**

Remplacer le contenu complet de `app/Http/Controllers/Api/PaymentWebhookController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Subscription\ActivateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $rawBody  = $request->getContent();
        $secret   = (string) config('services.netikash.webhook_secret', '');
        $received = (string) $request->header('X-Netikash-Signature', '');
        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $received)) {
            Log::warning('Netikash webhook: signature invalide', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event     = $request->input('event', '');
        $reference = $request->input('reference', '');

        if ($event !== 'payment.success') {
            return response()->json(['status' => 'ignored']);
        }

        $subscription = Subscription::where('payment_reference', $reference)->first();

        if (! $subscription) {
            return response()->json(['error' => 'Référence introuvable'], 404);
        }

        if ($subscription->status === 'confirmed') {
            return response()->json(['status' => 'already_confirmed']);
        }

        $entreprise = Entreprise::findOrFail($subscription->entreprise_id);

        (new ActivateSubscriptionAction())->execute(
            entreprise:       $entreprise,
            plan:             $subscription->plan,
            durationMonths:   (int) $subscription->expires_at->diffInMonths($subscription->starts_at),
            amount:           (float) $subscription->amount,
            paymentMethod:    'netikash',
            paymentReference: $reference,
        );

        return response()->json(['status' => 'activated']);
    }
}
```

- [ ] **Step 4 : Vérifier que les tests passent**

```bash
php artisan test --filter PaymentWebhookNetikashTest
```

Résultat attendu : 5 tests, 5 passed.

- [ ] **Step 5 : Lancer la suite complète pour vérifier absence de régressions**

```bash
php artisan test
```

Résultat attendu : tous les tests passent.

- [ ] **Step 6 : Commit**

```bash
git add app/Http/Controllers/Api/PaymentWebhookController.php tests/Feature/PaymentWebhookNetikashTest.php
git commit -m "feat(netikash): PaymentWebhookController adapté format Netikash + HMAC"
```

---

## Task 6 : Frontend — Abonnement/Index.vue

**Files:**
- Modify: `resources/js/Pages/Abonnement/Index.vue`

- [ ] **Step 1 : Remplacer le composant complet**

Remplacer le contenu de `resources/js/Pages/Abonnement/Index.vue` :

```vue
<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import axios from 'axios'
import AppDashboardLayout from '@/layouts/AppDashboardLayout.vue'

defineOptions({ layout: AppDashboardLayout })

const props = defineProps<{
    plan: string
    plan_expires_at: string | null
    jours_restants: number | null
    historique: Array<{
        id: number
        plan: string
        amount: number
        status: string
        payment_method: string | null
        payment_reference: string | null
        starts_at: string | null
        expires_at: string | null
        created_at: string
    }>
    prices: { premium: number; pro: number }
    promo_prices: { premium?: Record<number, number>; pro?: Record<number, number> }
    usd_to_cdf_rate: number
}>()

// ── État formulaire ──────────────────────────────────────────────────────────

const form = ref({
    plan:   'premium' as 'premium' | 'pro',
    duree:  1,
    phone:  '',
    devise: 'USD' as 'USD' | 'CDF',
})

// ── États UI ─────────────────────────────────────────────────────────────────

type Etape = 'formulaire' | 'attente' | 'succes' | 'timeout' | 'erreur'

const etape      = ref<Etape>('formulaire')
const erreurMsg  = ref('')
const reference  = ref('')
let   pollingId: ReturnType<typeof setInterval> | null = null
let   tentatives = 0

// ── Calcul du montant ────────────────────────────────────────────────────────

const montantUsd = computed((): number => {
    const promo = props.promo_prices[form.value.plan]
    if (promo && promo[form.value.duree] !== undefined) {
        return promo[form.value.duree]
    }
    return (props.prices[form.value.plan] ?? 0) * form.value.duree
})

const montantAffiche = computed((): string => {
    if (form.value.devise === 'CDF') {
        const cdf = Math.round(montantUsd.value * props.usd_to_cdf_rate)
        return `${cdf.toLocaleString('fr-FR')} CDF`
    }
    return `${montantUsd.value} $`
})

const aReduction = computed((): boolean => {
    const promo = props.promo_prices[form.value.plan]
    return !!(promo && promo[form.value.duree] !== undefined)
})

const montantSansReduction = computed((): string => {
    const base = (props.prices[form.value.plan] ?? 0) * form.value.duree
    if (form.value.devise === 'CDF') {
        return `${Math.round(base * props.usd_to_cdf_rate).toLocaleString('fr-FR')} CDF`
    }
    return `${base} $`
})

// ── Labels ───────────────────────────────────────────────────────────────────

const planLabel = computed(() => ({ free: 'Free', premium: 'Premium', pro: 'Pro' })[props.plan] ?? props.plan)

const planColor = computed(() => ({
    free:    'bg-gray-100 text-gray-700',
    premium: 'bg-blue-100 text-blue-800',
    pro:     'bg-purple-100 text-purple-800',
})[props.plan] ?? 'bg-gray-100 text-gray-700')

const joursWarning = computed(() => props.jours_restants !== null && props.jours_restants <= 7)

function statusLabel(status: string): string {
    return ({ pending: 'En attente', confirmed: 'Confirmé', expired: 'Expiré', failed: 'Échoué' })[status] ?? status
}

function statusColor(status: string): string {
    return ({
        pending:   'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-green-100 text-green-800',
        expired:   'bg-red-100 text-red-800',
        failed:    'bg-red-100 text-red-800',
    })[status] ?? 'bg-gray-100 text-gray-700'
}

// ── Actions ──────────────────────────────────────────────────────────────────

async function payer(): Promise<void> {
    erreurMsg.value = ''
    etape.value     = 'attente'
    tentatives      = 0

    try {
        const { data } = await axios.post('/abonnement/payer', {
            plan:   form.value.plan,
            duree:  form.value.duree,
            phone:  form.value.phone,
            devise: form.value.devise,
        })

        reference.value = data.reference
        demarrerPolling()
    } catch (err: any) {
        erreurMsg.value = err.response?.data?.message ?? 'Erreur lors de l\'initiation du paiement.'
        etape.value     = 'erreur'
    }
}

function demarrerPolling(): void {
    pollingId = setInterval(async () => {
        tentatives++

        if (tentatives > 60) {
            arreterPolling()
            etape.value = 'timeout'
            return
        }

        try {
            const { data } = await axios.get(`/api/abonnement/statut/${reference.value}`)

            if (data.status === 'confirmed') {
                arreterPolling()
                etape.value = 'succes'
            } else if (data.status === 'failed') {
                arreterPolling()
                erreurMsg.value = 'Le paiement a échoué. Réessayez.'
                etape.value     = 'erreur'
            }
        } catch {
            // réseau temporairement indisponible — continuer le polling
        }
    }, 5000)
}

function arreterPolling(): void {
    if (pollingId) {
        clearInterval(pollingId)
        pollingId = null
    }
}

function recommencer(): void {
    arreterPolling()
    erreurMsg.value = ''
    reference.value = ''
    etape.value     = 'formulaire'
}

onUnmounted(arreterPolling)
</script>

<template>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-8">

        <!-- En-tête plan actuel -->
        <div class="bg-white rounded-2xl shadow p-6 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex-1">
                <p class="text-sm text-gray-500 mb-1">Plan actuel</p>
                <div class="flex items-center gap-3">
                    <span :class="['px-3 py-1 rounded-full text-sm font-bold', planColor]">
                        {{ planLabel }}
                    </span>
                    <span v-if="plan !== 'free' && plan_expires_at" class="text-sm text-gray-600">
                        Valide jusqu'au
                        <strong>{{ new Date(plan_expires_at).toLocaleDateString('fr-FR') }}</strong>
                    </span>
                </div>
                <div v-if="joursWarning && plan !== 'free'"
                     class="mt-3 flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-sm text-yellow-800">
                    <span>⚠</span>
                    <span>Expire dans <strong>{{ jours_restants }} jour{{ jours_restants !== 1 ? 's' : '' }}</strong> — pensez à renouveler.</span>
                </div>
            </div>
            <div v-if="plan === 'free'" class="text-sm text-gray-400 italic">
                Passez à Premium ou Pro pour débloquer toutes les fonctionnalités.
            </div>
        </div>

        <!-- État : FORMULAIRE -->
        <div v-if="etape === 'formulaire'" class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-5">Souscrire / Renouveler</h2>

            <form @submit.prevent="payer" class="space-y-5">

                <!-- Choix du plan -->
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="form.plan = 'premium'"
                            :class="['border-2 rounded-xl p-4 text-left transition',
                                     form.plan === 'premium' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-200']">
                        <p class="font-bold text-gray-800">Premium</p>
                        <p class="text-blue-600 font-bold text-lg">{{ prices.premium }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Illimité · Exports · Créances</p>
                    </button>
                    <button type="button" @click="form.plan = 'pro'"
                            :class="['border-2 rounded-xl p-4 text-left transition',
                                     form.plan === 'pro' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-200']">
                        <p class="font-bold text-gray-800">Pro</p>
                        <p class="text-purple-600 font-bold text-lg">{{ prices.pro }} $/mois</p>
                        <p class="text-xs text-gray-500 mt-1">Tout Premium · Succursales</p>
                    </button>
                </div>

                <!-- Durée -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée</label>
                    <select v-model="form.duree"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]">
                        <option v-for="n in 12" :key="n" :value="n">
                            {{ n }} mois
                            <template v-if="promo_prices[form.plan]?.[n]">
                                — {{ promo_prices[form.plan][n] }}$ (réduit)
                            </template>
                            <template v-else>
                                — {{ (prices[form.plan] ?? 0) * n }}$
                            </template>
                        </option>
                    </select>
                </div>

                <!-- Devise -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Devise</label>
                    <div class="flex gap-3">
                        <button type="button" @click="form.devise = 'USD'"
                                :class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
                                         form.devise === 'USD' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']">
                            USD ($)
                        </button>
                        <button type="button" @click="form.devise = 'CDF'"
                                :class="['flex-1 border-2 rounded-lg py-2 text-sm font-semibold transition',
                                         form.devise === 'CDF' ? 'border-[#1A56A0] bg-blue-50 text-[#1A56A0]' : 'border-gray-200 text-gray-600']">
                            CDF (FC)
                        </button>
                    </div>
                    <p v-if="form.devise === 'CDF'" class="text-xs text-gray-500 mt-1">
                        Taux appliqué : 1 $ = {{ usd_to_cdf_rate.toLocaleString('fr-FR') }} FC
                    </p>
                </div>

                <!-- Téléphone -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Numéro Mobile Money
                    </label>
                    <input v-model="form.phone" type="tel" required
                           placeholder="Ex : 243 812 345 678"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1A56A0]" />
                    <p class="text-xs text-gray-500 mt-1">MTN MoMo, Airtel Money, Orange Money, M-Pesa</p>
                </div>

                <!-- Récap montant -->
                <div class="bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-700">
                    <div class="flex items-center justify-between">
                        <span>Total à payer</span>
                        <div class="text-right">
                            <span v-if="aReduction" class="line-through text-gray-400 text-xs mr-2">
                                {{ montantSansReduction }}
                            </span>
                            <strong class="text-gray-900 text-base">{{ montantAffiche }}</strong>
                        </div>
                    </div>
                    <p v-if="aReduction" class="text-green-600 text-xs mt-1 font-medium">
                        Offre spéciale {{ form.duree }} mois
                    </p>
                </div>

                <button type="submit"
                        :disabled="!form.phone"
                        class="w-full bg-[#1A56A0] hover:bg-[#0B2D5E] disabled:opacity-50 text-white font-semibold py-3 rounded-xl transition text-sm">
                    Payer {{ montantAffiche }} avec Netikash
                </button>

            </form>
        </div>

        <!-- État : EN ATTENTE -->
        <div v-if="etape === 'attente'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 mx-auto">
                <svg class="animate-spin w-8 h-8 text-[#1A56A0]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Confirmez sur votre téléphone</h3>
            <p class="text-gray-500 text-sm">
                Un message USSD a été envoyé sur votre numéro.<br>
                Acceptez le paiement pour activer votre abonnement.
            </p>
            <p class="text-xs text-gray-400">Vérification automatique en cours…</p>
        </div>

        <!-- État : SUCCÈS -->
        <div v-if="etape === 'succes'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-50 mx-auto">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Paiement confirmé !</h3>
            <p class="text-gray-500 text-sm">Votre abonnement est maintenant actif. Un email de confirmation vous a été envoyé.</p>
            <button @click="() => window.location.reload()"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Voir mon plan
            </button>
        </div>

        <!-- État : TIMEOUT -->
        <div v-if="etape === 'timeout'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-50 mx-auto">
                <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Délai expiré</h3>
            <p class="text-gray-500 text-sm">
                Le paiement n'a pas été confirmé dans les 5 minutes.<br>
                Si vous avez accepté le paiement sur votre téléphone, l'abonnement s'activera automatiquement dès confirmation.
            </p>
            <button @click="recommencer"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Réessayer
            </button>
        </div>

        <!-- État : ERREUR -->
        <div v-if="etape === 'erreur'" class="bg-white rounded-2xl shadow p-8 text-center space-y-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-50 mx-auto">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Paiement échoué</h3>
            <p class="text-gray-500 text-sm">{{ erreurMsg || 'Une erreur est survenue.' }}</p>
            <button @click="recommencer"
                    class="bg-[#1A56A0] text-white px-6 py-2 rounded-xl text-sm font-semibold hover:bg-[#0B2D5E] transition">
                Réessayer
            </button>
        </div>

        <!-- Historique -->
        <div v-if="historique.length" class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-lg font-bold text-gray-800 mb-4">Historique des abonnements</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-2 pr-4">Plan</th>
                            <th class="pb-2 pr-4">Montant</th>
                            <th class="pb-2 pr-4">Statut</th>
                            <th class="pb-2 pr-4">Début</th>
                            <th class="pb-2">Fin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in historique" :key="s.id" class="border-b last:border-0">
                            <td class="py-2 pr-4 font-medium capitalize">{{ s.plan }}</td>
                            <td class="py-2 pr-4">{{ s.amount }} $</td>
                            <td class="py-2 pr-4">
                                <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', statusColor(s.status)]">
                                    {{ statusLabel(s.status) }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 text-gray-500">{{ s.starts_at ?? '—' }}</td>
                            <td class="py-2 text-gray-500">{{ s.expires_at ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>
```

- [ ] **Step 2 : Vérifier la compilation**

```bash
npm run build 2>&1 | tail -20
```

Résultat attendu : `✓ built in` sans erreur TypeScript ni import manquant.

- [ ] **Step 3 : Tester manuellement le formulaire**

```bash
php artisan serve
npm run dev
```

Ouvrir `http://localhost:8000/abonnement`, vérifier :
- [ ] Sélection plan → montant se met à jour
- [ ] Sélection 6 mois → prix réduit affiché (barré + réduit)
- [ ] Toggle USD/CDF → conversion affichée avec taux
- [ ] Champ téléphone → bouton actif seulement si rempli

- [ ] **Step 4 : Commit**

```bash
git add resources/js/Pages/Abonnement/Index.vue
git commit -m "feat(netikash): page Abonnement avec formulaire Netikash et polling"
```

---

## Task 7 : Configuration webhook dans Netikash + tests end-to-end

**Files:** aucun — configuration dashboard uniquement

- [ ] **Step 1 : Configurer l'URL webhook dans le dashboard Netikash**

1. Aller sur `https://gateway.netikash.com/developer/applications`
2. Trouver le champ "Webhook URL" ou "Callback URL"
3. Saisir : `https://primegest.app/api/v1/payment/webhook`
4. Générer ou saisir un secret (32+ caractères aléatoires)
5. Copier ce secret dans `.env` : `NETIKASH_WEBHOOK_SECRET=ton_secret_ici`
6. Relancer les workers : `php artisan queue:restart`

- [ ] **Step 2 : Vérifier les variables d'environnement en production**

```bash
php artisan tinker --execute="echo config('services.netikash.client_id') ? 'OK' : 'MANQUANT';"
php artisan tinker --execute="echo config('services.netikash.webhook_secret') ? 'OK' : 'MANQUANT';"
```

Résultat attendu : `OK` pour les deux.

- [ ] **Step 3 : Test de paiement réel (staging)**

Utiliser un numéro de test Netikash si disponible, sinon tester avec un vrai numéro :
1. Aller sur `/abonnement`
2. Choisir Premium, 1 mois, USD
3. Saisir un numéro Mobile Money valide
4. Cliquer "Payer"
5. Confirmer le prompt USSD sur le téléphone
6. Vérifier que le polling détecte `confirmed` et affiche le succès

- [ ] **Step 4 : Commit final**

```bash
php artisan test
git add .
git commit -m "feat(netikash): intégration complète paiement Mobile Money"
```

---

## Checklist de self-review

- [x] PricingService couvre tous les cas (promo 6/12 mois, CDF, plan invalide)
- [x] NetikashService cache le token Redis, normalise le téléphone
- [x] AbonnementController calcule le montant côté serveur uniquement
- [x] status 'failed' : migration ENUM + modèle Subscription existant (string, pas besoin d'update)
- [x] Webhook : HMAC sur le body brut avant tout parsing
- [x] Webhook idempotent : `already_confirmed` si double appel
- [x] Polling : s'arrête à 60 tentatives (5 min) + `onUnmounted`
- [x] Route statut : filtrée par `entreprise_id` (pas de fuite inter-enterprise)
- [x] Taux CDF passé en prop Inertia (pas hardcodé en frontend)
