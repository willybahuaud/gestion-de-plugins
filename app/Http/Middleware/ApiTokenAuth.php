<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Middleware d'authentification par API Token pour les sites de vente WordPress.
     * Le token doit être envoyé dans le header Authorization: Bearer {token}
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token API manquant',
            ], 401);
        }

        $apiToken = ApiToken::findByPlainToken($token);

        if (!$apiToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token API invalide',
            ], 401);
        }

        if (!$apiToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Token API expiré',
            ], 401);
        }

        if ($ability && !$apiToken->hasAbility($ability)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission insuffisante pour cette action',
            ], 403);
        }

        // Marquer le token comme utilisé
        $apiToken->markAsUsed();

        // Stocker le token dans la requête pour utilisation ultérieure
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
