<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('domain');
            $table->string('ip_address', 45)->nullable()->after('is_active');
            $table->string('local_ip', 45)->nullable()->after('ip_address');
            $table->timestamp('deactivated_at')->nullable()->after('last_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('activations', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'ip_address', 'local_ip', 'deactivated_at']);
        });
    }
};
