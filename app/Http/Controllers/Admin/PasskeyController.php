<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

class PasskeyController extends Controller
{
    /**
     * Show passkey management page.
     */
    public function index(): View
    {
        $admin = Auth::guard('admin')->user();
        $passkeys = $admin->webAuthnCredentials()->get();

        return view('admin.passkeys.index', compact('passkeys'));
    }

    /**
     * Get attestation options to register a new passkey.
     */
    public function registerOptions(AttestationRequest $request): JsonResponse
    {
        // Le middleware guard:admin permet à $request->user() de fonctionner
        return response()->json($request->toCreate());
    }

    /**
     * Store a new passkey.
     */
    public function register(AttestedRequest $request): JsonResponse
    {
        $alias = $request->input('name', 'Ma cle de securite');

        // save() utilise $request->user() automatiquement grâce au middleware guard:admin
        $request->save(['alias' => $alias]);

        return response()->json([
            'success' => true,
            'message' => 'Passkey enregistree avec succes.',
        ]);
    }

    /**
     * Get assertion options to authenticate with passkey.
     */
    public function loginOptions(AssertionRequest $request): Responsable
    {
        $email = $request->input('email');
        $admin = AdminUser::where('email', $email)->first();

        if (! $admin || $admin->webAuthnCredentials()->count() === 0) {
            abort(404, 'Aucune passkey configuree pour ce compte.');
        }

        return $request->toVerify($admin);
    }

    /**
     * Authenticate with passkey.
     */
    public function login(AssertedRequest $request): JsonResponse
    {
        $success = $request->login('admin', remember: $request->boolean('remember'));

        if (! $success) {
            return response()->json([
                'error' => 'Authentification echouee.',
            ], 401);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => route('admin.dashboard'),
        ]);
    }

    /**
     * Delete a passkey.
     */
    public function destroy(string $id): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        $credential = $admin->webAuthnCredentials()->findOrFail($id);

        // Check that admin has at least one passkey or password
        $remainingPasskeys = $admin->webAuthnCredentials()->where('id', '!=', $id)->count();

        if ($remainingPasskeys === 0 && empty($admin->password)) {
            return back()->with('error', 'Impossible de supprimer la derniere passkey sans mot de passe configure.');
        }

        $credential->delete();

        return back()->with('success', 'Passkey supprimee avec succes.');
    }
}
