<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->boolean('is_dev_domain')->default(false)->after('is_active');
            $table->string('production_domain')->nullable()->after('is_dev_domain');
        });
    }

    public function down(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->dropColumn(['is_dev_domain', 'production_domain']);
        });
    }
};
