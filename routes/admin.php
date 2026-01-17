<?php

use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
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
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes protégées
Route::middleware('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Products
    Route::resource('products', ProductController::class)->names('admin.products');

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
});
