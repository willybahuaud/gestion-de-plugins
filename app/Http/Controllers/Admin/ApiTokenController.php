<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function index(): View
    {
        $tokens = ApiToken::latest()->paginate(20);
        return view('admin.api-tokens.index', compact('tokens'));
    }

    public function create(): View
    {
        return view('admin.api-tokens.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $tokenData = ApiToken::generateToken();

        $token = ApiToken::create([
            'name' => $validated['name'],
            'token' => $tokenData['hashed'],
            'abilities' => $validated['abilities'] ?? ['*'],
            'expires_at' => $validated['expires_at'],
        ]);

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('success', 'Token créé avec succès.')
            ->with('plain_token', $tokenData['plain']);
    }

    public function destroy(ApiToken $apiToken): RedirectResponse
    {
        $apiToken->delete();

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('success', 'Token supprimé avec succès.');
    }
}
