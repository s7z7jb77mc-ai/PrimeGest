<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\SubscriptionConfirmed;
use App\Models\Entreprise;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private const PLANS = [
        'premium' => ['1' => 7, '6' => 40, '12' => 70],
        'pro'     => ['1' => 10, '6' => 55, '12' => 100],
    ];

    /**
     * Initier un paiement Netikash (OAuth2 + Mobile Money).
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan'     => ['required', 'in:premium,pro'],
            'duration' => ['required', 'in:1,6,12'],
            'method'   => ['required', 'in:mpesa,airtel,orange'],
            'phone'    => ['required', 'string', 'min:9', 'max:15'],
        ]);

        $user       = $request->user();
        $entreprise = $user->entreprise;

        if (!$entreprise) {
            return response()->json(['message' => 'Entreprise introuvable.'], 422);
        }

        $plan      = $validated['plan'];
        $duration  = $validated['duration'];
        $amountUsd = self::PLANS[$plan][$duration];
        $amountCdf = $amountUsd * config('services.netikash.usd_to_cdf', 2800);
        $orderId   = 'PG-' . strtoupper(Str::random(10));

        // Créer la souscription en attente
        $subscription = Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $plan,
            'amount'            => $amountUsd,
            'payment_method'    => $validated['method'],
            'payment_reference' => $orderId,
            'netikash_order_id' => $orderId,
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonths((int) $duration),
        ]);

        // Mode sandbox — simuler la réponse Netikash sans appel réel
        if (config('services.netikash.sandbox')) {
            $txnId = 'SANDBOX-' . $orderId;
            $subscription->update(['netikash_transaction_id' => $txnId]);

            Log::info('[SANDBOX] Paiement simulé', [
                'entreprise' => $entreprise->name,
                'plan'       => $plan,
                'amount_usd' => $amountUsd,
                'order_id'   => $orderId,
                'txn_id'     => $txnId,
            ]);

            return response()->json([
                'success'        => true,
                'transaction_id' => $txnId,
                'sandbox'        => true,
                'message'        => '[SANDBOX] Paiement simulé — utilisez /payment/sandbox-confirm pour confirmer.',
            ]);
        }

        // Production — OAuth2 + appel Netikash réel
        $token = $this->getAccessToken();

        if (!$token) {
            $subscription->delete();
            return response()->json(['message' => 'Impossible de se connecter au service de paiement.'], 503);
        }

        $netikashResult = $this->callNetikash($token, $validated, $amountCdf, $orderId, $entreprise);

        if (!$netikashResult['success']) {
            $subscription->delete();
            return response()->json([
                'message' => $netikashResult['error'] ?? 'Paiement refusé. Vérifiez votre numéro.',
            ], 422);
        }

        $txnId = $netikashResult['transaction_id'] ?? $orderId;

        $subscription->update([
            'netikash_transaction_id' => $txnId,
            'netikash_payload'        => $netikashResult['payload'] ?? null,
        ]);

        Log::info('Paiement Netikash initié', [
            'entreprise' => $entreprise->name,
            'plan'       => $plan,
            'amount_usd' => $amountUsd,
            'amount_cdf' => $amountCdf,
            'txn_id'     => $txnId,
        ]);

        return response()->json([
            'success'        => true,
            'transaction_id' => $txnId,
            'message'        => 'Confirmez le paiement sur votre téléphone.',
        ]);
    }

    /**
     * Sandbox uniquement — confirme manuellement un paiement en attente pour tester.
     */
    public function sandboxConfirm(Request $request): JsonResponse
    {
        abort_unless(config('services.netikash.sandbox'), 403, 'Mode sandbox désactivé.');

        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
            'status'         => ['sometimes', 'in:success,failed'],
        ]);

        $txnId  = $validated['transaction_id'];
        $status = $validated['status'] ?? 'success';

        $subscription = Subscription::where(function ($q) use ($txnId) {
            $q->where('netikash_transaction_id', $txnId)
              ->orWhere('netikash_order_id', $txnId)
              ->orWhere('payment_reference', $txnId);
        })->where('status', 'pending')->first();

        if (!$subscription) {
            return response()->json(['message' => 'Souscription en attente introuvable.'], 404);
        }

        $payload = ['status' => $status, 'sandbox' => true, 'transaction_id' => $txnId];

        if ($status === 'success') {
            $this->activatePlan($subscription, $payload);
            Log::info('[SANDBOX] Plan activé manuellement', ['txn_id' => $txnId]);
            return response()->json(['success' => true, 'message' => 'Plan activé (sandbox).']);
        }

        $subscription->update(['status' => 'expired', 'netikash_payload' => $payload]);
        return response()->json(['success' => true, 'message' => 'Paiement marqué échoué (sandbox).']);
    }

    /**
     * Statut d'un paiement — polled par le frontend en mode "waiting".
     */
    public function status(Request $request, string $txnId): JsonResponse
    {
        $subscription = Subscription::where(function ($q) use ($txnId) {
            $q->where('netikash_transaction_id', $txnId)
              ->orWhere('netikash_order_id', $txnId)
              ->orWhere('payment_reference', $txnId);
        })
        ->where('entreprise_id', $request->user()->entreprise_id)
        ->latest()
        ->first();

        if (!$subscription) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json([
            'status' => $subscription->status,
            'plan'   => $subscription->plan,
        ]);
    }

    /**
     * Webhook Netikash — appelé automatiquement après confirmation PIN.
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Netikash callback reçu', $payload);

        // Vérifier la signature du webhook
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Netikash callback: signature invalide');
            return response()->json(['status' => 'unauthorized'], 401);
        }

        $orderId = $payload['order_id']
            ?? $payload['merchant_reference']
            ?? $payload['reference']
            ?? null;

        $txnId  = $payload['transaction_id'] ?? $payload['txn_id'] ?? null;
        $status = strtolower($payload['status'] ?? $payload['transaction_status'] ?? '');

        if (!$orderId && !$txnId) {
            Log::warning('Netikash callback sans identifiant', $payload);
            return response()->json(['status' => 'ignored'], 200);
        }

        $subscription = Subscription::where(function ($q) use ($orderId, $txnId) {
            $q->where('netikash_order_id', $orderId)
              ->orWhere('netikash_transaction_id', $txnId)
              ->orWhere('payment_reference', $orderId);
        })->where('status', 'pending')->first();

        if (!$subscription) {
            Log::warning('Netikash callback: subscription introuvable', compact('orderId', 'txnId'));
            return response()->json(['status' => 'not_found'], 200);
        }

        if (in_array($status, ['success', 'successful', 'completed', 'paid'])) {
            $this->activatePlan($subscription, $payload);
        } elseif (in_array($status, ['failed', 'cancelled', 'expired', 'rejected'])) {
            $subscription->update([
                'status'           => 'expired',
                'netikash_payload' => $payload,
            ]);
            Log::info('Paiement Netikash échoué', compact('orderId', 'status'));
        }

        return response()->json(['status' => 'ok'], 200);
    }

    // ── Privé ────────────────────────────────────────────────────────

    private function getAccessToken(): ?string
    {
        return Cache::remember('netikash_access_token', 3500, function () {
            $authUrl      = config('services.netikash.auth_url');
            $clientId     = config('services.netikash.client_id');
            $clientSecret = config('services.netikash.client_secret');

            if (!$authUrl || !$clientId || !$clientSecret) {
                Log::error('Netikash: variables .env manquantes (NETIKASH_AUTH_URL, CLIENT_ID, CLIENT_SECRET)');
                return null;
            }

            try {
                // OAuth2 RFC6749 : Basic Auth + application/x-www-form-urlencoded
                $response = Http::timeout(15)
                    ->withBasicAuth($clientId, $clientSecret)
                    ->asForm()
                    ->post($authUrl, ['grant_type' => 'client_credentials']);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('Netikash auth échouée', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            } catch (\Throwable $e) {
                Log::error('Netikash auth exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    private function callNetikash(string $token, array $validated, int $amountCdf, string $orderId, Entreprise $entreprise): array
    {
        $apiUrl = config('services.netikash.url');

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->post($apiUrl . '/exposed/v1/trs/collect', [
                    'order_id'     => $orderId,
                    'amount'       => $amountCdf,
                    'currency'     => 'CDF',
                    'phone'        => $validated['phone'],
                    'channel'      => strtoupper($validated['method']),
                    'description'  => "PrimeGest {$validated['plan']} — {$entreprise->name}",
                    'callback_url' => url('/api/v1/payment/webhook'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'        => true,
                    'transaction_id' => $data['transaction_id'] ?? $data['txn_id'] ?? $orderId,
                    'payload'        => $data,
                ];
            }

            Log::warning('Netikash paiement refusé', ['status' => $response->status(), 'body' => $response->body()]);

            return [
                'success' => false,
                'error'   => $response->json('message') ?? 'Paiement refusé par l\'opérateur.',
            ];
        } catch (\Throwable $e) {
            Log::error('Netikash exception paiement: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Service de paiement temporairement indisponible.'];
        }
    }

    private function activatePlan(Subscription $subscription, array $payload): void
    {
        $entreprise = $subscription->entreprise;

        $entreprise->update([
            'plan'            => $subscription->plan,
            'plan_expires_at' => $subscription->expires_at,
        ]);

        $subscription->update([
            'status'           => 'confirmed',
            'netikash_payload' => $payload,
        ]);

        Log::info('Plan activé automatiquement via Netikash', [
            'entreprise' => $entreprise->name,
            'plan'       => $subscription->plan,
            'expires_at' => $subscription->expires_at,
        ]);

        // Envoyer l'email de confirmation au propriétaire
        $owner = $entreprise->users()->orderBy('id')->first();
        if ($owner?->email) {
            try {
                Mail::to($owner->email)->send(new SubscriptionConfirmed(
                    userName:      $owner->name,
                    entrepriseName: $entreprise->name,
                    plan:          $subscription->plan,
                    expireDate:    $subscription->expires_at->format('d/m/Y'),
                    amount:        (float) $subscription->amount,
                    isTrial:       false,
                    trialDays:     0,
                    appUrl:        config('app.url'),
                ));
            } catch (\Throwable $e) {
                Log::warning('Email confirmation abonnement non envoyé: ' . $e->getMessage());
            }
        }
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.netikash.webhook_secret');

        if (!$secret) {
            return true; // pas de secret configuré → pas de vérification
        }

        // Adapter selon la doc Netikash (header ou champ body)
        $signature = $request->header('X-Netikash-Signature')
            ?? $request->header('X-Signature')
            ?? $request->input('signature');

        if (!$signature) {
            return true; // Netikash n'envoie peut-être pas encore de signature
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
