<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Models\Entreprise;
use App\Models\Subscription;
use App\Services\NetikashService;
use App\Services\PricingService;
use Illuminate\Support\Str;

class InitierPaiementAction
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly NetikashService $netikash,
    ) {}

    public function execute(Entreprise $entreprise, string $plan, int $duree, string $devise): array
    {
        $montant   = $this->pricing->calculate($plan, $duree, $devise);
        $reference = $this->generateUniqueReference($entreprise->id);

        $subscription = Subscription::create([
            'entreprise_id'     => $entreprise->id,
            'plan'              => $plan,
            'amount'            => $montant,
            'payment_method'    => 'netikash',
            'payment_reference' => $reference,
            'status'            => 'pending',
            'starts_at'         => now(),
            'expires_at'        => now()->addMonths($duree),
        ]);

        try {
            $result = $this->netikash->initiatePayment(
                amount:    $montant,
                currency:  $devise,
                reference: $reference,
                label:     'Abonnement PrimeGest '.ucfirst($plan).' '.$duree.' mois',
            );
        } catch (\Throwable $e) {
            $subscription->update(['status' => 'failed']);
            throw $e;
        }

        // Extraire l'ID de la requête Netikash depuis le link pour le polling
        $link      = $result['link'] ?? '';
        $requestId = $link ? basename(parse_url($link, PHP_URL_PATH)) : null;

        $subscription->update([
            'netikash_transaction_id' => $result['trans'] ?? null,
            'netikash_order_id'       => $requestId,
        ]);

        return [
            'subscription' => $subscription,
            'checkout_url' => $link,
            'request_id'   => $requestId,
        ];
    }

    private function generateUniqueReference(int $entrepriseId): string
    {
        do {
            $reference = 'PG-'.$entrepriseId.'-'.strtoupper(Str::random(8));
        } while (Subscription::where('payment_reference', $reference)->exists());

        return $reference;
    }
}
