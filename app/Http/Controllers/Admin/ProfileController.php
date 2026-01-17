<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.profile.edit', [
            'admin' => Auth::guard('admin')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admin_users,email,' . $admin->id,
        ]);

        $admin->update($validated);

        return back()->with('success', 'Profil mis a jour.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Auth::guard('admin')->user();

        if (!Hash::check($validated['current_password'], $admin->password)) {
            return back()->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }

        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Mot de passe modifie avec succes.');
    }
}
