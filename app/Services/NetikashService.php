<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetikashService
{
    private string $baseUrl;

    private string $authUrl;

    private string $clientId;

    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl      = (string) config('services.netikash.base_url', 'https://api.netikash.com/api/v1/trs');
        $this->authUrl      = (string) config('services.netikash.auth_url', 'https://accounts.netikash.com/oauth2/token');
        $this->clientId     = (string) config('services.netikash.client_id', '');
        $this->clientSecret = (string) config('services.netikash.client_secret', '');
    }

    public function getAccessToken(): string
    {
        $cached = Cache::get('netikash_token');
        if ($cached !== null) {
            return (string) $cached;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(15)
            ->asForm()
            ->post($this->authUrl, [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::error('Netikash: échec token OAuth2', [
                'auth_url' => $this->authUrl,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: impossible d\'obtenir le token d\'accès.');
        }

        $token     = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $ttl       = max(60, $expiresIn - 60);

        Cache::put('netikash_token', $token, $ttl);

        return $token;
    }

    /**
     * Crée une demande de paiement Netikash (flux cli-payments).
     * Retourne le checkout link vers lequel rediriger l'utilisateur.
     */
    public function initiatePayment(
        float $amount,
        string $currency,
        string $reference,
        string $label,
    ): array {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->post($this->baseUrl.'/transactions/requests/cli-payments', [
                'amount'      => $amount,
                'currency'    => $currency,
                'ref'         => $reference,
                'referer_url' => url('/abonnement?payment=return&ref='.urlencode($reference)),
                'label'       => $label,
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['error'] ?? false) === true) {
            Log::error('Netikash: échec initiation paiement', [
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: échec de l\'initiation — '.($body['message'] ?? $response->body()));
        }

        Log::info('Netikash: paiement initié', [
            'reference' => $reference,
            'trans'     => $body['trans'] ?? null,
            'link'      => $body['link'] ?? null,
        ]);

        return $body;
    }

    public function getPaymentStatus(string $netikashRequestId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->timeout(15)
            ->get($this->baseUrl.'/transactions/requests/'.urlencode($netikashRequestId));

        if (! $response->successful()) {
            throw new \RuntimeException('Netikash: impossible de vérifier le statut — '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function verifyWebhookSignature(string $rawBody, string $signature, string $timestamp, string $secret): bool
    {
        $payload  = $timestamp.'.'.$rawBody;
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
