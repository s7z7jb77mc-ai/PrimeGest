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
        $this->baseUrl = (string) config('services.netikash.base_url', 'https://gateway.netikash.com');
        $this->clientId = (string) config('services.netikash.client_id', '');
        $this->clientSecret = (string) config('services.netikash.client_secret', '');
        $this->tokenPath = (string) config('services.netikash.token_path', '/oauth/token');
        $this->paymentPath = (string) config('services.netikash.payment_path', '/api/v1/payment/initiate');
    }

    public function getAccessToken(): string
    {
        $cached = Cache::get('netikash_access_token');
        if ($cached !== null) {
            return (string) $cached;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl.$this->tokenPath, [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::error('Netikash: échec token OAuth2', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Netikash: impossible d\'obtenir le token d\'accès.');
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $ttl = max(60, $expiresIn - 60);

        Cache::put('netikash_access_token', $token, $ttl);

        return $token;
    }

    public function initiatePayment(
        string $phone,
        float $amount,
        string $currency,
        string $reference,
        string $description,
    ): array {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($this->baseUrl.$this->paymentPath, [
                'phone' => $this->normalizePhone($phone),
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
                'description' => $description,
                'callback_url' => url('/api/v1/payment/webhook'),
            ]);

        if (! $response->successful()) {
            Log::error('Netikash: échec initiation paiement', [
                'reference' => $reference,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Netikash: échec de l\'initiation du paiement — '.$response->body());
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
