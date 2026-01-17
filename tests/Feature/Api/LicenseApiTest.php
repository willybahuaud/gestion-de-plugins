<?php

namespace Tests\Feature\Api;

use App\Models\Activation;
use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseApiTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private Price $price;
    private User $user;
    private License $license;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create(['slug' => 'test-plugin']);
        $this->price = Price::factory()->create(['product_id' => $this->product->id]);
        $this->user = User::factory()->create();
        $this->license = License::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'price_id' => $this->price->id,
            'status' => 'active',
            'max_activations' => 3,
        ]);
    }

    public function test_verify_returns_error_for_invalid_license(): void
    {
        $response = $this->postJson('/api/license/verify', [
            'license_key' => 'invalid-uuid',
            'product_slug' => $this->product->slug,
            'domain' => 'example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => false,
                'reason' => 'license_not_found',
            ]);
    }

    public function test_verify_returns_error_for_wrong_product(): void
    {
        $otherProduct = Product::factory()->create(['slug' => 'other-plugin']);

        $response = $this->postJson('/api/license/verify', [
            'license_key' => $this->license->license_key,
            'product_slug' => $otherProduct->slug,
            'domain' => 'example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => false,
                'reason' => 'product_mismatch',
            ]);
    }

    public function test_verify_returns_error_for_expired_license(): void
    {
        $this->license->update([
            'status' => 'expired',
            'expires_at' => now()->subDays(10),
        ]);

        $response = $this->postJson('/api/license/verify', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => false,
                'reason' => 'license_expired',
            ]);
    }

    public function test_verify_returns_error_for_non_activated_domain(): void
    {
        $response = $this->postJson('/api/license/verify', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'not-activated.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => false,
                'reason' => 'domain_not_activated',
            ]);
    }

    public function test_verify_returns_valid_for_activated_domain(): void
    {
        Activation::factory()->create([
            'license_id' => $this->license->id,
            'domain' => 'example.com',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/license/verify', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'https://www.example.com/',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'valid' => true,
            ])
            ->assertJsonPath('license.uuid', $this->license->license_key);
    }

    public function test_activate_creates_new_activation(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'newsite.com',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Licence activée avec succès',
            ]);

        $this->assertDatabaseHas('activations', [
            'license_id' => $this->license->id,
            'domain' => 'newsite.com',
            'is_active' => true,
        ]);
    }

    public function test_activate_reuses_existing_activation(): void
    {
        Activation::factory()->create([
            'license_id' => $this->license->id,
            'domain' => 'existing.com',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'https://existing.com/',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Licence déjà activée sur ce domaine',
            ]);
    }

    public function test_activate_fails_when_limit_reached(): void
    {
        $this->license->update(['max_activations' => 2]);

        Activation::factory()->count(2)->create([
            'license_id' => $this->license->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'newsite.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Limite d\'activations atteinte',
            ]);
    }

    public function test_deactivate_removes_activation(): void
    {
        Activation::factory()->create([
            'license_id' => $this->license->id,
            'domain' => 'todeactivate.com',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/license/deactivate', [
            'license_key' => $this->license->license_key,
            'domain' => 'todeactivate.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Licence désactivée avec succès',
            ]);

        $this->assertDatabaseHas('activations', [
            'license_id' => $this->license->id,
            'domain' => 'todeactivate.com',
            'is_active' => false,
        ]);
    }

    public function test_deactivate_fails_for_non_existent_activation(): void
    {
        $response = $this->postJson('/api/license/deactivate', [
            'license_key' => $this->license->license_key,
            'domain' => 'notactivated.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_validation_errors_for_missing_fields(): void
    {
        $response = $this->postJson('/api/license/verify', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_key', 'product_slug', 'domain']);
    }
}
