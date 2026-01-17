<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Price;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LicenseController extends Controller
{
    public function index(Request $request): View
    {
        $query = License::with('user', 'product');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($productId = $request->get('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($q) => $q->where('email', 'like', "%{$search}%"));
            });
        }

        $licenses = $query->latest()->paginate(20);
        $products = Product::orderBy('name')->get();

        return view('admin.licenses.index', compact('licenses', 'products'));
    }

    public function show(License $license): View
    {
        $license->load(['user', 'product', 'price', 'activations']);

        return view('admin.licenses.show', compact('license'));
    }

    public function create(): View
    {
        $users = User::orderBy('email')->get();
        $products = Product::with('prices')->where('is_active', true)->orderBy('name')->get();

        return view('admin.licenses.create', compact('users', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'price_id' => 'nullable|exists:prices,id',
            'type' => 'required|in:lifetime,subscription',
            'activations_limit' => 'required|integer|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $license = License::create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $validated['user_id'],
            'product_id' => $validated['product_id'],
            'price_id' => $validated['price_id'],
            'status' => 'active',
            'type' => $validated['type'],
            'activations_limit' => $validated['activations_limit'],
            'expires_at' => $validated['expires_at'],
        ]);

        return redirect()
            ->route('admin.licenses.show', $license)
            ->with('success', 'Licence créée avec succès.');
    }

    public function edit(License $license): View
    {
        return view('admin.licenses.edit', compact('license'));
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,expired,revoked',
            'activations_limit' => 'required|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $license->update($validated);

        return redirect()
            ->route('admin.licenses.show', $license)
            ->with('success', 'Licence mise à jour avec succès.');
    }

    public function revoke(License $license): RedirectResponse
    {
        $license->update(['status' => 'revoked']);

        // Désactiver toutes les activations
        $license->activations()->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        return back()->with('success', 'Licence révoquée avec succès.');
    }

    public function reactivate(License $license): RedirectResponse
    {
        $license->update(['status' => 'active']);

        return back()->with('success', 'Licence réactivée avec succès.');
    }
}
