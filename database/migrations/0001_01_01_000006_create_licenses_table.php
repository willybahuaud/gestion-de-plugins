<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('license_key')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('price_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'expired', 'suspended', 'refunded'])->default('active');
            $table->timestamp('expires_at')->nullable(); // Null si lifetime
            $table->integer('max_activations')->default(1);
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->integer('grace_period_days')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
