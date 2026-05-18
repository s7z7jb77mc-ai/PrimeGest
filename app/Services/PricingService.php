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

        if ($duree < 1 || $duree > 12) {
            throw new \InvalidArgumentException("La durée doit être comprise entre 1 et 12 mois.");
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
