<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\UpdateController;
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

// Routes licences (appelées par les plugins WordPress installés - pas d'auth requise)
// HMAC signature optionnelle pour l'instant (mode: optional), peut etre rendu obligatoire avec hmac:required
Route::prefix('license')->middleware('hmac')->group(function () {
    Route::post('/verify', [LicenseController::class, 'verify'])->middleware('throttle:license-verify');
    Route::post('/activate', [LicenseController::class, 'activate'])->middleware('throttle:license-action');
    Route::post('/deactivate', [LicenseController::class, 'deactivate'])->middleware('throttle:license-action');
});

// Routes mises à jour (appelées par les plugins WordPress installés)
Route::prefix('update')->middleware('hmac')->group(function () {
    Route::post('/check', [UpdateController::class, 'checkUpdate'])->middleware('throttle:update-check');
    Route::get('/download', [UpdateController::class, 'download'])->name('api.update.download');
});

// Routes authentifiées par API Token (pour les sites de vente WordPress)
Route::middleware('api.token')->group(function () {
    // Authentification SSO
    Route::prefix('auth')->middleware('throttle:api-auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
        Route::post('/password/reset', [AuthController::class, 'resetPassword']);
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

    // Licences - Gestion par l'utilisateur connecté
    Route::prefix('licenses')->group(function () {
        Route::get('/activations', [LicenseController::class, 'getActivations']);
        Route::delete('/activations/{activationId}', [LicenseController::class, 'deactivateById']);
    });
});
