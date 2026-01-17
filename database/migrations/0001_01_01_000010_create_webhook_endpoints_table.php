<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: "Site vente Plugin A"
            $table->string('url');
            $table->string('secret'); // Clé secrète pour signature HMAC
            $table->json('events'); // Liste des événements écoutés
            $table->json('product_ids')->nullable(); // Filtrer par produits
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
