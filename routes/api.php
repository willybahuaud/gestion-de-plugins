<?php

use App\Http\Controllers\Api\AuthController;
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

// Routes authentifiées par API Token (pour les sites de vente WordPress)
Route::middleware('api.token')->group(function () {
    // Authentification SSO
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
});

// Routes authentifiées par JWT (pour les utilisateurs connectés)
Route::middleware('auth:api')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user', [AuthController::class, 'update']);
    });
});
