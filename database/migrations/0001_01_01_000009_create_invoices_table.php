<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_invoice_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('license_id')->nullable()->constrained()->onDelete('set null');
            $table->string('number'); // Numéro Stripe
            $table->integer('amount_total'); // En centimes
            $table->integer('amount_tax')->default(0); // En centimes
            $table->string('currency', 3)->default('EUR');
            $table->enum('status', ['paid', 'void', 'uncollectible'])->default('paid');
            $table->string('stripe_pdf_url')->nullable();
            $table->string('local_pdf_path')->nullable(); // Copie locale Backblaze
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
