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
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);
    }

    public function test_get_access_token_appelle_oauth_endpoint(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_access_token');

        $service = new NetikashService;
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

        $service = new NetikashService;
        $service->getAccessToken();
        $service->getAccessToken(); // 2e appel

        Http::assertSentCount(1); // une seule requête HTTP
    }

    public function test_initiate_payment_envoie_bonne_requete(): void
    {
        Cache::forget('netikash_access_token');

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            '*/payment/initiate' => Http::response([
                'transaction_id' => 'TXN-123',
                'status' => 'pending',
                'message' => 'USSD envoyé',
            ], 200),
        ]);

        $service = new NetikashService;
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
        $service = new NetikashService;
        $this->assertSame('243812345678', $service->normalizePhone('+243812345678'));
    }

    public function test_normalise_phone_avec_00(): void
    {
        $service = new NetikashService;
        $this->assertSame('243812345678', $service->normalizePhone('00243812345678'));
    }

    public function test_normalise_phone_avec_espaces(): void
    {
        $service = new NetikashService;
        $this->assertSame('243812345678', $service->normalizePhone(' 243 812 345 678 '));
    }
}
