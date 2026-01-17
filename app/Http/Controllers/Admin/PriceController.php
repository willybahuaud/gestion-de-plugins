<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Price;
use App\Models\Product;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceController extends Controller
{
    public function create(Product $product): View
    {
        return view('admin.prices.create', compact('product'));
    }

    public function store(Request $request, Product $product, StripeService $stripeService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:recurring,one_time',
            'amount' => 'required|numeric|min:0',
            'interval' => 'required_if:type,recurring|nullable|in:month,year',
            'max_activations' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        // Convertir le montant en centimes
        $validated['amount'] = (int) ($validated['amount'] * 100);
        $validated['currency'] = 'EUR';
        $validated['is_active'] = $request->boolean('is_active', true);

        // Si one_time, pas d'interval
        if ($validated['type'] === 'one_time') {
            $validated['interval'] = null;
        }

        $price = $product->prices()->create($validated);

        // Synchroniser avec Stripe
        try {
            $stripeService->syncPrice($price);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création
            report($e);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Prix créé avec succès.');
    }

    public function edit(Product $product, Price $price): View
    {
        return view('admin.prices.edit', compact('product', 'price'));
    }

    public function update(Request $request, Product $product, Price $price): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_activations' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        // Note: On ne peut pas modifier type/amount/interval car le prix Stripe est immutable
        // Si besoin de changer ces valeurs, il faut créer un nouveau prix

        $price->update($validated);

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Prix mis à jour avec succès.');
    }

    public function destroy(Product $product, Price $price): RedirectResponse
    {
        if ($price->licenses()->exists()) {
            return back()->with('error', 'Impossible de supprimer un prix avec des licences associées.');
        }

        $price->delete();

        return redirect()
            ->route('admin.products.show', $product)
            ->with('success', 'Prix supprimé avec succès.');
    }
}
