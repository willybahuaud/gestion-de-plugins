<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Changer le type de colonne pour accommoder les valeurs chiffrées (plus longues)
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->text('secret')->change();
        });

        // 2. Chiffrer les secrets existants (stockés en clair)
        $endpoints = DB::table('webhook_endpoints')->get();

        foreach ($endpoints as $endpoint) {
            // Vérifie si la valeur semble déjà chiffrée (commence par eyJ)
            if (!str_starts_with($endpoint->secret, 'eyJ')) {
                DB::table('webhook_endpoints')
                    ->where('id', $endpoint->id)
                    ->update([
                        'secret' => Crypt::encryptString($endpoint->secret),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Déchiffrer les secrets
        $endpoints = DB::table('webhook_endpoints')->get();

        foreach ($endpoints as $endpoint) {
            if (str_starts_with($endpoint->secret, 'eyJ')) {
                try {
                    $decrypted = Crypt::decryptString($endpoint->secret);
                    DB::table('webhook_endpoints')
                        ->where('id', $endpoint->id)
                        ->update(['secret' => $decrypted]);
                } catch (\Exception $e) {
                    // Déjà en clair ou erreur de déchiffrement
                }
            }
        }

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->string('secret')->change();
        });
    }
};
