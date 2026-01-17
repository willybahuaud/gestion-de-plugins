<?php

namespace Tests\Unit\Models;

use App\Models\Activation;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_uuid_on_creation(): void
    {
        $license = License::factory()->create(['license_key' => null]);

        $this->assertNotNull($license->license_key);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $license->license_key
        );
    }

    public function test_is_active_returns_true_for_active_status(): void
    {
        $license = License::factory()->active()->make();

        $this->assertTrue($license->isActive());
    }

    public function test_is_active_returns_false_for_non_active_status(): void
    {
        $license = License::factory()->expired()->make();

        $this->assertFalse($license->isActive());
    }

    public function test_is_expired_returns_true_for_expired_status(): void
    {
        $license = License::factory()->expired()->make();

        $this->assertTrue($license->isExpired());
    }

    public function test_is_expired_returns_true_for_past_expiration_date(): void
    {
        $license = License::factory()->make([
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($license->isExpired());
    }

    public function test_is_expired_returns_false_for_active_with_future_date(): void
    {
        $license = License::factory()->make([
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertFalse($license->isExpired());
    }

    public function test_is_lifetime_returns_true_for_null_expires_at(): void
    {
        $license = License::factory()->lifetime()->make();

        $this->assertTrue($license->isLifetime());
    }

    public function test_is_lifetime_returns_false_for_set_expires_at(): void
    {
        $license = License::factory()->make([
            'expires_at' => now()->addYear(),
        ]);

        $this->assertFalse($license->isLifetime());
    }

    public function test_can_activate_returns_false_for_non_active_license(): void
    {
        $license = License::factory()->expired()->create();

        $this->assertFalse($license->canActivate());
    }

    public function test_can_activate_returns_true_for_unlimited_activations(): void
    {
        $license = License::factory()->unlimited()->create();

        $this->assertTrue($license->canActivate());
    }

    public function test_can_activate_returns_true_when_under_limit(): void
    {
        $license = License::factory()->create(['max_activations' => 3]);

        $this->assertTrue($license->canActivate());
    }

    public function test_can_activate_returns_false_when_at_limit(): void
    {
        $license = License::factory()->create(['max_activations' => 2]);

        Activation::factory()->count(2)->create(['license_id' => $license->id]);

        $this->assertFalse($license->canActivate());
    }

    public function test_activations_count_attribute(): void
    {
        $license = License::factory()->create();
        Activation::factory()->count(3)->create(['license_id' => $license->id]);

        $this->assertEquals(3, $license->activations_count);
    }

    public function test_remaining_activations_returns_correct_count(): void
    {
        $license = License::factory()->create(['max_activations' => 5]);
        Activation::factory()->count(2)->create(['license_id' => $license->id]);

        $this->assertEquals(3, $license->remaining_activations);
    }

    public function test_remaining_activations_returns_negative_one_for_unlimited(): void
    {
        $license = License::factory()->unlimited()->create();

        $this->assertEquals(-1, $license->remaining_activations);
    }

    public function test_remaining_activations_never_goes_below_zero(): void
    {
        $license = License::factory()->create(['max_activations' => 2]);
        Activation::factory()->count(5)->create(['license_id' => $license->id]);

        $this->assertEquals(0, $license->remaining_activations);
    }
}
