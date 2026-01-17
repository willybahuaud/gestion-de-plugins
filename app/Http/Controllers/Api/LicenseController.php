<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activation;
use App\Models\License;
use App\Models\Product;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseController extends Controller
{
    public function __construct(
        private WebhookService $webhookService
    ) {}
    /**
     * Vérifier la validité d'une licence
     * Appelé par les plugins WordPress installés
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'product_slug' => 'required|string',
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $license = License::where('uuid', $request->license_key)->first();

        if (!$license) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'reason' => 'license_not_found',
                'message' => 'Licence non trouvée',
            ]);
        }

        $product = Product::where('slug', $request->product_slug)->first();

        if (!$product || $license->product_id !== $product->id) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'reason' => 'product_mismatch',
                'message' => 'Cette licence n\'est pas valide pour ce produit',
            ]);
        }

        if ($license->isExpired()) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'reason' => 'license_expired',
                'message' => 'Licence expirée',
                'expired_at' => $license->expires_at->toIso8601String(),
            ]);
        }

        if ($license->status !== 'active') {
            return response()->json([
                'success' => true,
                'valid' => false,
                'reason' => 'license_' . $license->status,
                'message' => 'Licence ' . $this->translateStatus($license->status),
            ]);
        }

        // Vérifier si le domaine est activé
        $normalizedDomain = Activation::normalizeDomain($request->domain);
        $activation = $license->activations()
            ->where('domain', $normalizedDomain)
            ->where('is_active', true)
            ->first();

        if (!$activation) {
            return response()->json([
                'success' => true,
                'valid' => false,
                'reason' => 'domain_not_activated',
                'message' => 'Ce domaine n\'est pas activé pour cette licence',
                'can_activate' => $license->canActivate(),
                'activations_count' => $license->activations()->where('is_active', true)->count(),
                'activations_limit' => $license->activations_limit,
            ]);
        }

        // Mettre à jour la date de dernière vérification
        $activation->update(['last_check_at' => now()]);

        return response()->json([
            'success' => true,
            'valid' => true,
            'license' => [
                'uuid' => $license->uuid,
                'type' => $license->type,
                'status' => $license->status,
                'expires_at' => $license->expires_at?->toIso8601String(),
                'is_lifetime' => $license->isLifetime(),
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'activation' => [
                'domain' => $activation->domain,
                'activated_at' => $activation->activated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Activer une licence sur un domaine
     */
    public function activate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'product_slug' => 'required|string',
            'domain' => 'required|string',
            'local_ip' => 'nullable|ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $license = License::where('uuid', $request->license_key)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'Licence non trouvée',
            ], 404);
        }

        $product = Product::where('slug', $request->product_slug)->first();

        if (!$product || $license->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette licence n\'est pas valide pour ce produit',
            ], 400);
        }

        if ($license->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Licence expirée',
            ], 400);
        }

        if ($license->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Licence ' . $this->translateStatus($license->status),
            ], 400);
        }

        $normalizedDomain = Activation::normalizeDomain($request->domain);

        // Vérifier si déjà activé sur ce domaine
        $existingActivation = $license->activations()
            ->where('domain', $normalizedDomain)
            ->first();

        if ($existingActivation) {
            if ($existingActivation->is_active) {
                return response()->json([
                    'success' => true,
                    'message' => 'Licence déjà activée sur ce domaine',
                    'activation' => [
                        'id' => $existingActivation->id,
                        'domain' => $existingActivation->domain,
                        'activated_at' => $existingActivation->activated_at->toIso8601String(),
                    ],
                ]);
            }

            // Réactiver une activation désactivée
            $existingActivation->update([
                'is_active' => true,
                'activated_at' => now(),
                'ip_address' => $request->ip(),
                'local_ip' => $request->local_ip,
                'last_check_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Licence réactivée sur ce domaine',
                'activation' => [
                    'id' => $existingActivation->id,
                    'domain' => $existingActivation->domain,
                    'activated_at' => $existingActivation->activated_at->toIso8601String(),
                ],
            ]);
        }

        // Vérifier la limite d'activations
        if (!$license->canActivate()) {
            return response()->json([
                'success' => false,
                'message' => 'Limite d\'activations atteinte',
                'activations_count' => $license->activations()->where('is_active', true)->count(),
                'activations_limit' => $license->activations_limit,
            ], 400);
        }

        // Créer l'activation
        $activation = Activation::create([
            'license_id' => $license->id,
            'domain' => $normalizedDomain,
            'ip_address' => $request->ip(),
            'local_ip' => $request->local_ip,
            'is_active' => true,
            'activated_at' => now(),
            'last_check_at' => now(),
        ]);

        // Déclencher le webhook d'activation
        $this->webhookService->licenseActivated($license, $normalizedDomain);

        return response()->json([
            'success' => true,
            'message' => 'Licence activée avec succès',
            'activation' => [
                'id' => $activation->id,
                'domain' => $activation->domain,
                'activated_at' => $activation->activated_at->toIso8601String(),
            ],
            'activations_count' => $license->activations()->where('is_active', true)->count(),
            'activations_limit' => $license->activations_limit,
        ], 201);
    }

    /**
     * Désactiver une licence d'un domaine
     */
    public function deactivate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_key' => 'required|string',
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $license = License::where('uuid', $request->license_key)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'Licence non trouvée',
            ], 404);
        }

        $normalizedDomain = Activation::normalizeDomain($request->domain);

        $activation = $license->activations()
            ->where('domain', $normalizedDomain)
            ->where('is_active', true)
            ->first();

        if (!$activation) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune activation trouvée pour ce domaine',
            ], 404);
        }

        $activation->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        // Déclencher le webhook de désactivation
        $this->webhookService->licenseDeactivated($license, $normalizedDomain);

        return response()->json([
            'success' => true,
            'message' => 'Licence désactivée avec succès',
            'activations_count' => $license->activations()->where('is_active', true)->count(),
            'activations_limit' => $license->activations_limit,
        ]);
    }

    /**
     * Récupérer les activations d'une licence (pour l'utilisateur connecté)
     */
    public function getActivations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'license_uuid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = auth()->user();
        $license = $user->licenses()->where('uuid', $request->license_uuid)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'message' => 'Licence non trouvée',
            ], 404);
        }

        $activations = $license->activations()
            ->orderBy('activated_at', 'desc')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'domain' => $a->domain,
                'is_active' => $a->is_active,
                'activated_at' => $a->activated_at?->toIso8601String(),
                'deactivated_at' => $a->deactivated_at?->toIso8601String(),
                'last_check_at' => $a->last_check_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'activations' => $activations,
            'activations_active_count' => $license->activations()->where('is_active', true)->count(),
            'activations_limit' => $license->activations_limit,
        ]);
    }

    /**
     * Désactiver une activation par ID (pour l'utilisateur connecté)
     */
    public function deactivateById(Request $request, int $activationId): JsonResponse
    {
        $user = auth()->user();

        $activation = Activation::where('id', $activationId)
            ->whereHas('license', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$activation) {
            return response()->json([
                'success' => false,
                'message' => 'Activation non trouvée',
            ], 404);
        }

        $activation->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activation désactivée avec succès',
        ]);
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'suspended' => 'suspendue',
            'expired' => 'expirée',
            'revoked' => 'révoquée',
            default => $status,
        };
    }
}
