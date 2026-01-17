<?php

namespace Database\Factories;

use App\Models\Price;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'stripe_price_id' => 'price_' . Str::random(14),
            'name' => fake()->randomElement(['Licence solo', 'Licence Pro', 'Licence Agency']),
            'type' => fake()->randomElement(['one_time', 'recurring']),
            'amount' => fake()->randomElement([2900, 4900, 9900, 14900]),
            'currency' => 'eur',
            'interval' => null,
            'max_activations' => fake()->randomElement([1, 3, 10, 0]),
            'is_active' => true,
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'recurring',
            'interval' => 'year',
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'one_time',
            'interval' => null,
        ]);
    }
}
