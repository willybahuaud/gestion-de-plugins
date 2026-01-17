<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'name' => ucwords($name),
            'description' => fake()->paragraph(),
            'stripe_product_id' => 'prod_' . Str::random(14),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
