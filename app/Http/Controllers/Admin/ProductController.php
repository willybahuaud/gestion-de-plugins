<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::withCount(['licenses', 'releases'])
            ->latest()
            ->paginate(20);

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.create');
    }

    public function store(Request $request, StripeService $stripeService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $product = Product::create($validated);

        // Synchroniser avec Stripe
        try {
            $stripeService->syncProduct($product);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Produit créé avec succès.');
    }

    public function show(Product $product): View
    {
        $product->load(['prices', 'releases' => fn($q) => $q->latest()->take(10)]);
        $licensesCount = $product->licenses()->count();
        $activeLicenses = $product->licenses()->where('status', 'active')->count();

        return view('admin.products.show', compact('product', 'licensesCount', 'activeLicenses'));
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product, StripeService $stripeService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug,' . $product->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $product->update($validated);

        // Synchroniser avec Stripe
        try {
            $stripeService->syncProduct($product);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la mise à jour
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Produit mis à jour avec succès.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->licenses()->exists()) {
            return back()->with('error', 'Impossible de supprimer un produit avec des licences.');
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Produit supprimé avec succès.');
    }
}
