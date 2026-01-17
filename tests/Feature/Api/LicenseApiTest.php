<?php

namespace Tests\Feature\Api;

use App\Models\Activation;
use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

        // Désactiver le rate limiting pour les tests
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Créer les données manuellement pour éviter les problèmes de factory
        $this->product = Product::create([
            'slug' => 'test-plugin',
            'name' => 'Test Plugin',
            'description' => 'A test plugin',
            'is_active' => true,
        ]);

        $this->price = Price::create([
            'product_id' => $this->product->id,
            'stripe_price_id' => 'price_test123',
            'name' => 'Licence Solo',
            'type' => 'one_time',
            'amount' => 4900,
            'currency' => 'eur',
            'max_activations' => 3,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();

        $this->license = License::create([
            'license_key' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'price_id' => $this->price->id,
            'status' => 'active',
            'max_activations' => 3,
            'expires_at' => now()->addYear(),
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
        $otherProduct = Product::create([
            'slug' => 'other-plugin',
            'name' => 'Other Plugin',
            'is_active' => true,
        ]);

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

        // Debug: vérifier les données
        $this->license->refresh();
        $debugInfo = sprintf(
            "License product_id: %d, Product id: %d, Product slug: %s",
            $this->license->product_id,
            $this->product->id,
            $this->product->slug
        );

        $response = $this->postJson('/api/license/verify', [
            'license_key' => $this->license->license_key,
            'product_slug' => $this->product->slug,
            'domain' => 'example.com',
        ]);

        $this->assertEquals(
            $this->product->id,
            $this->license->product_id,
            "Product ID mismatch: " . $debugInfo . " | Response: " . $response->getContent()
        );

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
        Activation::create([
            'license_id' => $this->license->id,
            'domain' => 'example.com',
            'is_active' => true,
            'activated_at' => now(),
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
        Activation::create([
            'license_id' => $this->license->id,
            'domain' => 'existing.com',
            'is_active' => true,
            'activated_at' => now(),
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

        // Créer 2 activations pour cette licence
        Activation::create([
            'license_id' => $this->license->id,
            'domain' => 'site1.com',
            'is_active' => true,
            'activated_at' => now(),
        ]);
        Activation::create([
            'license_id' => $this->license->id,
            'domain' => 'site2.com',
            'is_active' => true,
            'activated_at' => now(),
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
        Activation::create([
            'license_id' => $this->license->id,
            'domain' => 'todeactivate.com',
            'is_active' => true,
            'activated_at' => now(),
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
