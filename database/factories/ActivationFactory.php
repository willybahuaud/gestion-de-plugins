<?php

namespace Database\Factories;

use App\Models\Activation;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activation>
 */
class ActivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'domain' => fake()->domainName(),
            'activated_at' => now(),
            'last_check_at' => now(),
            'plugin_version' => '1.0.0',
        ];
    }
}
