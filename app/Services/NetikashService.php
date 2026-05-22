<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetikashService
{
    private bool $sandbox;

    private string $baseUrl;

    private string $authUrl;

    private string $clientId;

    private string $clientSecret;

    private string $paymentPath;

    public function __construct()
    {
        $this->sandbox      = (bool) config('services.netikash.sandbox', false);
        $this->baseUrl      = (string) config('services.netikash.base_url', 'https://gateway.netikash.com');
        $this->authUrl      = (string) config('services.netikash.auth_url', 'https://gateway.netikash.com/oauth/token');
        $this->clientId     = (string) config('services.netikash.client_id', '');
        $this->clientSecret = (string) config('services.netikash.client_secret', '');
        $this->paymentPath  = (string) config('services.netikash.payment_path', '/api/v1/payment/initiate');
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function getAccessToken(): string
    {
        $cacheKey = $this->sandbox ? 'netikash_token_sandbox' : 'netikash_token_prod';

        $cached = Cache::get($cacheKey);
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
                'sandbox'  => $this->sandbox,
                'auth_url' => $this->authUrl,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: impossible d\'obtenir le token d\'accès.');
        }

        $token     = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $ttl       = max(60, $expiresIn - 60);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function initiatePayment(
        string $phone,
        float $amount,
        string $currency,
        string $reference,
        string $description,
    ): array {
        if ($this->sandbox) {
            Log::info('Netikash [SANDBOX] initiation paiement', [
                'phone'     => $this->normalizePhone($phone),
                'amount'    => $amount,
                'currency'  => $currency,
                'reference' => $reference,
            ]);
        }

        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($this->baseUrl.$this->paymentPath, [
                'phone'        => $this->normalizePhone($phone),
                'amount'       => $amount,
                'currency'     => $currency,
                'reference'    => $reference,
                'description'  => $description,
                'callback_url' => url('/api/v1/payment/webhook'),
            ]);

        if (! $response->successful()) {
            Log::error('Netikash: échec initiation paiement', [
                'sandbox'   => $this->sandbox,
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: échec de l\'initiation du paiement — '.$response->body());
        }

        return $response->json() ?? [];
    }

    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', trim($phone));
        $phone = ltrim($phone, '+');
        $phone = (string) preg_replace('/^0+(?=[1-9])/', '', $phone);

        return $phone;
    }
}
