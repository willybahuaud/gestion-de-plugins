<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('version'); // Semver (ex: 1.2.3)
            $table->text('changelog')->nullable(); // Markdown
            $table->string('file_path'); // Chemin Backblaze B2
            $table->integer('file_size')->nullable(); // En bytes
            $table->string('file_hash')->nullable(); // SHA256
            $table->string('min_php_version')->nullable();
            $table->string('min_wp_version')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
