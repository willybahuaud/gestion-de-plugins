<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->onDelete('cascade');
            $table->string('event'); // Type d'événement
            $table->json('payload'); // Données envoyées
            $table->integer('response_status')->nullable(); // Code HTTP retour
            $table->text('response_body')->nullable();
            $table->integer('attempts')->default(1);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'sent_at']);
            $table->index(['event', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
