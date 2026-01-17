<x-admin-layout title="Cles de securite (Passkeys)">
    <div class="mb-6">
        <a href="{{ route('admin.profile.edit') }}" class="text-indigo-600 hover:underline">&larr; Retour au profil</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Cles de securite (Passkeys)</h1>
                <p class="text-gray-500 mt-1">Utilisez une cle de securite physique (YubiKey) ou biometrique (TouchID, FaceID) pour vous connecter sans mot de passe.</p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Compatible 1Password</h3>
                    <p class="mt-1 text-sm text-blue-700">Les passkeys sont compatibles avec 1Password, qui peut generer et stocker vos cles de securite de maniere securisee.</p>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        @endif

        <div class="mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Vos cles de securite</h2>

            @if($passkeys->count() > 0)
                <div class="divide-y divide-gray-200 border border-gray-200 rounded-md">
                    @foreach($passkeys as $passkey)
                        <div class="flex items-center justify-between p-4">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $passkey->name ?? 'Cle de securite' }}</p>
                                    <p class="text-sm text-gray-500">Ajoutee le {{ $passkey->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                            <form action="{{ route('admin.passkeys.destroy', $passkey->id) }}" method="POST"
                                onsubmit="return confirm('Supprimer cette cle de securite ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">Supprimer</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 border border-dashed border-gray-300 rounded-md">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    <p class="mt-2 text-gray-500">Aucune cle de securite configuree</p>
                </div>
            @endif
        </div>

        <div class="border-t border-gray-200 pt-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Ajouter une cle de securite</h2>

            <div class="mb-4">
                <label for="passkey-name" class="block text-sm font-medium text-gray-700 mb-1">Nom de la cle (optionnel)</label>
                <input type="text" id="passkey-name" placeholder="Ex: YubiKey bureau, 1Password"
                    class="w-full max-w-md px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <button type="button" id="register-passkey" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Ajouter une cle de securite
            </button>

            <div id="passkey-error" class="hidden mt-4 bg-red-50 border border-red-200 rounded-md p-4">
                <p class="text-sm text-red-700"></p>
            </div>

            <div id="passkey-success" class="hidden mt-4 bg-green-50 border border-green-200 rounded-md p-4">
                <p class="text-sm text-green-700"></p>
            </div>
        </div>
    </div>

    <!-- Laragear Webpass - Gestion automatique de l'encodage WebAuthn -->
    <script src="https://cdn.jsdelivr.net/npm/@laragear/webpass@2/dist/webpass.js" defer></script>

    <script defer>
        document.addEventListener('DOMContentLoaded', () => {
            const registerButton = document.getElementById('register-passkey');
            const passkeyNameInput = document.getElementById('passkey-name');
            const errorDiv = document.getElementById('passkey-error');
            const successDiv = document.getElementById('passkey-success');

            // Verifier le support WebAuthn
            if (typeof Webpass !== 'undefined' && Webpass.isUnsupported()) {
                errorDiv.querySelector('p').textContent = 'Votre navigateur ne supporte pas les Passkeys.';
                errorDiv.classList.remove('hidden');
                registerButton.disabled = true;
                return;
            }

            registerButton.addEventListener('click', async () => {
                errorDiv.classList.add('hidden');
                successDiv.classList.add('hidden');
                registerButton.disabled = true;
                registerButton.textContent = 'En attente...';

                try {
                    // Utiliser Webpass pour l'attestation (enregistrement)
                    const result = await Webpass.attest(
                        '{{ route('admin.passkeys.register-options') }}',
                        '{{ route('admin.passkeys.register') }}',
                        {
                            // Headers personnalises
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            // Donnees supplementaires a envoyer avec l'enregistrement
                            body: {
                                name: passkeyNameInput.value || 'Cle de securite',
                            },
                        }
                    );

                    if (result.success) {
                        successDiv.querySelector('p').textContent = result.data?.message || 'Passkey enregistree avec succes.';
                        successDiv.classList.remove('hidden');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        throw new Error(result.error?.message || result.error || 'Erreur inconnue');
                    }
                } catch (error) {
                    console.error('WebAuthn error:', error);
                    let message = 'Une erreur est survenue';

                    if (error.name === 'NotAllowedError') {
                        message = 'Operation annulee ou refusee par l\'utilisateur.';
                    } else if (error.name === 'InvalidStateError') {
                        message = 'Cette cle de securite est deja enregistree.';
                    } else if (error.name === 'NotSupportedError') {
                        message = 'Votre navigateur ne supporte pas ce type d\'authentification.';
                    } else if (error.message) {
                        message = error.message;
                    }

                    errorDiv.querySelector('p').textContent = message;
                    errorDiv.classList.remove('hidden');
                } finally {
                    registerButton.disabled = false;
                    registerButton.textContent = 'Ajouter une cle de securite';
                }
            });
        });
    </script>
</x-admin-layout>
