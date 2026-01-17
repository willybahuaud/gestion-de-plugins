<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Plugin Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@laragear/webpass@2/dist/webpass.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <h1 class="text-2xl font-bold text-center text-gray-900 mb-8">Plugin Hub Admin</h1>

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div id="passkey-error" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <p></p>
        </div>

        <form action="{{ route('admin.login') }}" method="POST" id="login-form">
            @csrf

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-6" id="password-field">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" id="password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Se souvenir de moi</span>
                </label>
            </div>

            <div class="space-y-3">
                <button type="submit" id="login-password"
                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Connexion
                </button>

                <button type="button" id="login-passkey"
                    class="w-full bg-gray-800 text-white py-2 px-4 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 flex items-center justify-center">
                    <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Connexion avec Passkey
                </button>
            </div>
        </form>
    </div>

    <script defer>
        document.addEventListener('DOMContentLoaded', () => {
            const loginPasskeyButton = document.getElementById('login-passkey');
            const emailInput = document.getElementById('email');
            const errorDiv = document.getElementById('passkey-error');
            const passkeyButtonHtml = loginPasskeyButton.innerHTML;

            loginPasskeyButton.addEventListener('click', async () => {
                const email = emailInput.value;
                if (!email) {
                    showError('Veuillez entrer votre email');
                    return;
                }

                errorDiv.classList.add('hidden');
                loginPasskeyButton.disabled = true;
                loginPasskeyButton.textContent = 'En attente de la cle...';

                try {
                    // Utiliser Webpass pour l'assertion (authentification)
                    const result = await Webpass.assert(
                        '{{ route('admin.passkey.login-options') }}',
                        '{{ route('admin.passkey.login') }}',
                        {
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                            // Envoyer l'email pour les options
                            optionsBody: {
                                email: email,
                            },
                            // Envoyer remember avec l'assertion
                            body: {
                                remember: document.getElementById('remember').checked,
                            },
                        }
                    );

                    if (result.success && result.data?.redirect) {
                        window.location.href = result.data.redirect;
                    } else if (result.success) {
                        window.location.href = '{{ route('admin.dashboard') }}';
                    } else {
                        throw new Error(result.error?.message || result.error || 'Authentification echouee');
                    }
                } catch (error) {
                    console.error('WebAuthn error:', error);
                    let message = 'Une erreur est survenue';

                    if (error.name === 'NotAllowedError') {
                        message = 'Operation annulee ou refusee par l\'utilisateur.';
                    } else if (error.message) {
                        message = error.message;
                    }

                    showError(message);
                } finally {
                    loginPasskeyButton.disabled = false;
                    loginPasskeyButton.innerHTML = passkeyButtonHtml;
                }
            });

            function showError(message) {
                errorDiv.querySelector('p').textContent = message;
                errorDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
