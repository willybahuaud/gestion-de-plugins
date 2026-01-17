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

        return view('admin.dashboard', compact('stats', 'recentLicenses', 'recentUsers'));
    }
}
