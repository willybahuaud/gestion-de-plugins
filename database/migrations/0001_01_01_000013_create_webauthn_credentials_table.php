<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('name')->nullable();
            $table->binary('credential_id');
            $table->text('public_key');
            $table->unsignedBigInteger('counter')->default(0);
            $table->json('transports')->nullable();
            $table->uuid('aaguid')->nullable();
            $table->string('attestation_format')->default('none');
            $table->timestamps();
            $table->softDeletes();

            $table->index('credential_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
