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
            '*/oauth2/token' => Http::response([
                'access_token' => 'fake-token-abc123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);
    }

    public function test_get_access_token_appelle_oauth_endpoint(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_token');

        $service = new NetikashService;
        $token = $service->getAccessToken();

        $this->assertSame('fake-token-abc123', $token);

        Http::assertSent(function (Request $req) {
            return str_contains($req->url(), '/oauth2/token')
                && $req->hasHeader('Authorization');
        });
    }

    public function test_token_est_mis_en_cache(): void
    {
        $this->fakeTokenResponse();
        Cache::forget('netikash_token');

        $service = new NetikashService;
        $service->getAccessToken();
        $service->getAccessToken(); // 2e appel

        Http::assertSentCount(1); // une seule requête HTTP
    }

    public function test_initiate_payment_envoie_bonne_requete(): void
    {
        Cache::forget('netikash_access_token');

        Http::fake([
            '*/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200),
            '*/transactions/requests/cli-payments' => Http::response([
                'trans'   => 'TXN-123',
                'link'    => 'https://pay.netikash.com/checkout/TXN-123',
                'status'  => 'pending',
                'message' => 'En attente de paiement',
            ], 200),
        ]);

        $service = new NetikashService;
        $result = $service->initiatePayment(
            amount: 7.0,
            currency: 'USD',
            reference: 'PG-1-ABC12345',
            label: 'Abonnement Premium 1 mois',
        );

        $this->assertArrayHasKey('trans', $result);
        $this->assertSame('TXN-123', $result['trans']);
    }

    public function test_get_access_token_leve_exception_si_echec_oauth(): void
    {
        Cache::forget('netikash_token');

        Http::fake([
            '*/oauth2/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $this->expectException(\RuntimeException::class);

        (new NetikashService)->getAccessToken();
    }
}
