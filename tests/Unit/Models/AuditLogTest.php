<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_creates_audit_entry(): void
    {
        $product = Product::factory()->create();

        $log = AuditLog::log('created', $product, null, ['name' => 'Test']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'model_type' => Product::class,
            'model_id' => $product->id,
        ]);
    }

    public function test_log_stores_old_and_new_values(): void
    {
        $product = Product::factory()->create();

        $log = AuditLog::log(
            'updated',
            $product,
            ['name' => 'Old Name'],
            ['name' => 'New Name']
        );

        $this->assertEquals(['name' => 'Old Name'], $log->old_values);
        $this->assertEquals(['name' => 'New Name'], $log->new_values);
    }

    public function test_model_label_for_product(): void
    {
        $product = Product::factory()->create(['name' => 'My Plugin']);
        $log = AuditLog::log('created', $product);

        $this->assertEquals('Product: My Plugin', $log->model_label);
    }

    public function test_model_label_for_deleted_model(): void
    {
        $product = Product::factory()->create(['name' => 'My Plugin']);
        $log = AuditLog::log('deleted', $product);
        $product->delete();

        // Refresh to clear the cached relation
        $log->refresh();

        $this->assertStringContains('Product', $log->model_label);
    }

    public function test_model_label_for_null_model(): void
    {
        $log = AuditLog::create([
            'action' => 'login',
            'model_type' => null,
            'model_id' => null,
            'created_at' => now(),
        ]);

        $this->assertEquals('-', $log->model_label);
    }
}
