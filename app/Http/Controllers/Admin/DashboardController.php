<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users_count' => User::count(),
            'products_count' => Product::count(),
            'licenses_active' => License::where('status', 'active')->count(),
            'licenses_total' => License::count(),
            'revenue_month' => Invoice::where('status', 'paid')
                ->whereMonth('issued_at', now()->month)
                ->whereYear('issued_at', now()->year)
                ->sum('amount_total') / 100,
            'revenue_year' => Invoice::where('status', 'paid')
                ->whereYear('issued_at', now()->year)
                ->sum('amount_total') / 100,
        ];

        $recentLicenses = License::with('user', 'product')
            ->latest()
            ->take(10)
            ->get();

        $recentUsers = User::latest()
            ->take(10)
            ->get();

        // Alertes
        $alerts = [
            // Licences suspendues (paiement échoué)
            'suspended_licenses' => License::with(['user', 'product'])
                ->where('status', 'suspended')
                ->orderByDesc('updated_at')
                ->take(5)
                ->get(),

            // Licences expirant dans les 7 jours
            'expiring_soon' => License::with(['user', 'product'])
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(7)])
                ->orderBy('expires_at')
                ->take(10)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats', 'recentLicenses', 'recentUsers', 'alerts'));
    }
}
