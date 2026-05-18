<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entreprise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entreprise_id' => Entreprise::factory(),
            'plan' => fake()->randomElement(['free', 'premium', 'pro']),
            'amount' => fake()->randomElement([0, 7, 10]),
            'payment_method' => fake()->randomElement(['mobile_money', 'card', null]),
            'payment_reference' => fake()->optional()->uuid(),
            'status' => fake()->randomElement(['pending', 'confirmed', 'expired', 'failed', 'trial']),
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'confirmed_by' => null,
            'warning_sent_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'amount' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }
}
