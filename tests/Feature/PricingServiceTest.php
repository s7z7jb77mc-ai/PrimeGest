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
        $this->pricing = new PricingService;
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
