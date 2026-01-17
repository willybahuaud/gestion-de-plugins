<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('stripe_price_id')->nullable()->index();
            $table->string('name'); // Ex: "Licence annuelle", "Lifetime"
            $table->enum('type', ['recurring', 'one_time']);
            $table->integer('amount'); // En centimes
            $table->string('currency', 3)->default('EUR');
            $table->enum('interval', ['month', 'year'])->nullable(); // Pour recurring
            $table->integer('max_activations')->default(1); // 0 = illimité
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
