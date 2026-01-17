<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_key' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'price_id' => Price::factory(),
            'status' => 'active',
            'expires_at' => now()->addYear(),
            'max_activations' => 3,
            'stripe_subscription_id' => null,
            'grace_period_days' => 7,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(10),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    public function lifetime(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_activations' => 0,
        ]);
    }
}
