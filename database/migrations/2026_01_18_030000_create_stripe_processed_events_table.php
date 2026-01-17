<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_processed_events', function (Blueprint $table) {
            $table->string('event_id', 100)->primary();
            $table->string('event_type', 100);
            $table->timestamp('processed_at');

            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_processed_events');
    }
};
