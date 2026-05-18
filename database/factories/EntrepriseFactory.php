<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entreprise>
 */
class EntrepriseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'plan' => 'free',
            'plan_expires_at' => null,
            'storage_used_mb' => 0,
            'sync_version' => 1,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'premium',
            'plan_expires_at' => now()->addMonth(),
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'pro',
            'plan_expires_at' => now()->addMonth(),
        ]);
    }
}
