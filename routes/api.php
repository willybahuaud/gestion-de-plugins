<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes pour l'API du Plugin Hub
| Préfixe automatique: /api
|
*/

// Routes publiques (pas d'authentification requise)
Route::post('/auth/check-email', [AuthController::class, 'checkEmail']);

// Webhook Stripe (signature vérifiée dans le controller)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Routes authentifiées par API Token (pour les sites de vente WordPress)
Route::middleware('api.token')->group(function () {
    // Authentification SSO
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Stripe - Création de checkout (appelé par les sites WordPress)
    Route::post('/stripe/checkout', [StripeController::class, 'createCheckout']);
});

// Routes authentifiées par JWT (pour les utilisateurs connectés)
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user', [AuthController::class, 'update']);
    });

    // Stripe - Gestion des abonnements utilisateur
    Route::prefix('stripe')->group(function () {
        Route::get('/subscriptions', [StripeController::class, 'getSubscription']);
        Route::post('/cancel', [StripeController::class, 'cancelSubscription']);
        Route::post('/reactivate', [StripeController::class, 'reactivateSubscription']);
    });
});
