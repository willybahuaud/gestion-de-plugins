<?php

use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\PasskeyController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReleaseController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes pour le back-office d'administration
| Préfixe: /admin
|
*/

// Auth routes (publiques)
Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    // Vérification email pour passkey (rate limit 5/5min contre énumération)
    Route::post('/check-email', [AuthController::class, 'checkEmail'])
        ->middleware('throttle:5,5')
        ->name('admin.check-email');

    // Passkey authentication
    Route::post('/passkey/login-options', [PasskeyController::class, 'loginOptions'])->name('admin.passkey.login-options');
    Route::post('/passkey/login', [PasskeyController::class, 'login'])->name('admin.passkey.login');
});

// Routes protégées
// guard:admin définit le guard par défaut pour que $request->user() fonctionne
Route::middleware(['admin', 'guard:admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    // Ping pour vérifier la session (heartbeat)
    Route::get('/ping', fn () => response()->json(['ok' => true]))->name('admin.ping');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Products
    Route::resource('products', ProductController::class)->names('admin.products');

    // Prices (nested under products)
    Route::get('products/{product}/prices/create', [PriceController::class, 'create'])->name('admin.prices.create');
    Route::post('products/{product}/prices', [PriceController::class, 'store'])->name('admin.prices.store');
    Route::get('products/{product}/prices/{price}/edit', [PriceController::class, 'edit'])->name('admin.prices.edit');
    Route::put('products/{product}/prices/{price}', [PriceController::class, 'update'])->name('admin.prices.update');
    Route::delete('products/{product}/prices/{price}', [PriceController::class, 'destroy'])->name('admin.prices.destroy');

    // Releases (nested under products)
    Route::get('products/{product}/releases', [ReleaseController::class, 'index'])->name('admin.releases.index');
    Route::get('products/{product}/releases/create', [ReleaseController::class, 'create'])->name('admin.releases.create');
    Route::post('products/{product}/releases', [ReleaseController::class, 'store'])->name('admin.releases.store');
    Route::get('products/{product}/releases/{release}', [ReleaseController::class, 'show'])->name('admin.releases.show');
    Route::get('products/{product}/releases/{release}/edit', [ReleaseController::class, 'edit'])->name('admin.releases.edit');
    Route::put('products/{product}/releases/{release}', [ReleaseController::class, 'update'])->name('admin.releases.update');
    Route::delete('products/{product}/releases/{release}', [ReleaseController::class, 'destroy'])->name('admin.releases.destroy');
    Route::get('products/{product}/releases/{release}/download', [ReleaseController::class, 'download'])->name('admin.releases.download');

    // Users (clients)
    Route::resource('users', UserController::class)->names('admin.users')->except(['create', 'store', 'destroy']);

    // Licenses
    Route::resource('licenses', LicenseController::class)->names('admin.licenses')->except(['destroy']);
    Route::post('licenses/{license}/revoke', [LicenseController::class, 'revoke'])->name('admin.licenses.revoke');
    Route::post('licenses/{license}/reactivate', [LicenseController::class, 'reactivate'])->name('admin.licenses.reactivate');

    // API Tokens
    Route::resource('api-tokens', ApiTokenController::class)->names('admin.api-tokens')->only(['index', 'create', 'store', 'destroy']);

    // Profil admin
    Route::get('profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');

    // Passkeys management
    Route::get('passkeys', [PasskeyController::class, 'index'])->name('admin.passkeys.index');
    Route::post('passkeys/register-options', [PasskeyController::class, 'registerOptions'])->name('admin.passkeys.register-options');
    Route::post('passkeys', [PasskeyController::class, 'register'])->name('admin.passkeys.register');
    Route::delete('passkeys/{id}', [PasskeyController::class, 'destroy'])->name('admin.passkeys.destroy');
});
