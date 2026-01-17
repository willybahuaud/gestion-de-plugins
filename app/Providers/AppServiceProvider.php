<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for security.
     */
    protected function configureRateLimiting(): void
    {
        // Admin login: 5 attempts per 15 minutes per IP
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinutes(15, 5)->by($request->ip());
        });

        // License verify: 10 requests per hour per license key
        RateLimiter::for('license-verify', function (Request $request) {
            $licenseKey = $request->input('license_key', $request->ip());
            return Limit::perHour(10)->by($licenseKey);
        });

        // License activate/deactivate: 5 requests per hour per license key
        RateLimiter::for('license-action', function (Request $request) {
            $licenseKey = $request->input('license_key', $request->ip());
            return Limit::perHour(5)->by($licenseKey);
        });

        // API authentication: 10 attempts per minute per IP
        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Update check: 30 requests per hour per license
        RateLimiter::for('update-check', function (Request $request) {
            $licenseKey = $request->input('license_key', $request->ip());
            return Limit::perHour(30)->by($licenseKey);
        });
    }
}
