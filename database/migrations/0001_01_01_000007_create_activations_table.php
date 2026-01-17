<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->onDelete('cascade');
            $table->string('domain'); // Normalisé (sans www, sans protocole)
            $table->timestamp('activated_at');
            $table->timestamp('last_check_at')->nullable();
            $table->string('plugin_version')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations');
    }
};
