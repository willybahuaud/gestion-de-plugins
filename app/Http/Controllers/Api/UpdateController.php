<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\License;
use App\Models\Product;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class UpdateController extends Controller
{
    /**
     * Vérifier si une mise à jour est disponible
     */
    public function checkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'product_slug' => 'required|string',
            'domain' => 'required|string',
            'current_version' => 'required|string',
            'php_version' => 'nullable|string',
            'wp_version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier la licence
        $license = License::where('uuid', $request->license_key)->first();

        if (!$license || $license->status !== 'active' || $license->isExpired()) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'reason' => 'invalid_license',
                'message' => 'Licence invalide ou expirée',
            ]);
        }

        // Vérifier le produit
        $product = Product::where('slug', $request->product_slug)->first();

        if (!$product || $license->product_id !== $product->id) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'reason' => 'product_mismatch',
                'message' => 'Produit non reconnu',
            ]);
        }

        // Vérifier l'activation du domaine
        $normalizedDomain = Activation::normalizeDomain($request->domain);
        $activation = $license->activations()
            ->where('domain', $normalizedDomain)
            ->where('is_active', true)
            ->first();

        if (!$activation) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'reason' => 'domain_not_activated',
                'message' => 'Domaine non activé',
            ]);
        }

        // Récupérer la dernière release publiée
        $latestRelease = $product->releases()
            ->where('is_published', true)
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->first();

        if (!$latestRelease) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'reason' => 'no_release',
                'message' => 'Aucune version disponible',
            ]);
        }

        // Comparer les versions
        $currentVersion = $request->current_version;
        $comparison = version_compare($latestRelease->version, $currentVersion);

        if ($comparison <= 0) {
            return response()->json([
                'success' => true,
                'update_available' => false,
                'current_version' => $currentVersion,
                'latest_version' => $latestRelease->version,
                'message' => 'Vous êtes à jour',
            ]);
        }

        // Vérifier les prérequis PHP et WordPress
        $phpCompatible = true;
        $wpCompatible = true;
        $compatibilityWarnings = [];

        if ($latestRelease->min_php_version && $request->php_version) {
            if (version_compare($request->php_version, $latestRelease->min_php_version, '<')) {
                $phpCompatible = false;
                $compatibilityWarnings[] = "PHP {$latestRelease->min_php_version}+ requis (actuel: {$request->php_version})";
            }
        }

        if ($latestRelease->min_wp_version && $request->wp_version) {
            if (version_compare($request->wp_version, $latestRelease->min_wp_version, '<')) {
                $wpCompatible = false;
                $compatibilityWarnings[] = "WordPress {$latestRelease->min_wp_version}+ requis (actuel: {$request->wp_version})";
            }
        }

        // Mettre à jour last_check_at
        $activation->update(['last_check_at' => now()]);

        return response()->json([
            'success' => true,
            'update_available' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestRelease->version,
            'changelog' => $latestRelease->changelog,
            'published_at' => $latestRelease->published_at->toIso8601String(),
            'file_size' => $latestRelease->file_size,
            'file_size_formatted' => $latestRelease->formatted_file_size,
            'requirements' => [
                'min_php_version' => $latestRelease->min_php_version,
                'min_wp_version' => $latestRelease->min_wp_version,
            ],
            'compatible' => $phpCompatible && $wpCompatible,
            'compatibility_warnings' => $compatibilityWarnings,
            'download_url' => $this->generateDownloadUrl($license, $latestRelease),
        ]);
    }

    /**
     * Télécharger une release (URL signée)
     */
    public function download(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'release_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier la signature de l'URL
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'Lien de téléchargement expiré ou invalide',
            ], 403);
        }

        $license = License::where('uuid', $request->license_key)->first();

        if (!$license || $license->status !== 'active' || $license->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Licence invalide ou expirée',
            ], 403);
        }

        $release = Release::find($request->release_id);

        if (!$release || $release->product_id !== $license->product_id) {
            return response()->json([
                'success' => false,
                'message' => 'Release non trouvée',
            ], 404);
        }

        if (!$release->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette version n\'est pas encore disponible',
            ], 403);
        }

        // Vérifier que le fichier existe
        if (!$release->file_path || !Storage::disk('releases')->exists($release->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier non trouvé',
            ], 404);
        }

        $product = $release->product;
        $filename = $product->slug . '-' . $release->version . '.zip';

        return Storage::disk('releases')->download($release->file_path, $filename);
    }

    /**
     * Générer une URL de téléchargement signée (valide 1 heure)
     */
    private function generateDownloadUrl(License $license, Release $release): string
    {
        return URL::temporarySignedRoute(
            'api.update.download',
            now()->addHour(),
            [
                'license_key' => $license->uuid,
                'release_id' => $release->id,
            ]
        );
    }
}
