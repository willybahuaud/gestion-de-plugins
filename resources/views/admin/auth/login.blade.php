<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Plugin Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

        <div id="error-message" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <p></p>
        </div>

        <!-- Etape 1: Email -->
        <div id="step-email">
            <div class="mb-6">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="admin@example.com">
            </div>

            <button type="button" id="btn-continue"
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Continuer
            </button>
        </div>

        <!-- Etape 2a: Passkey disponible -->
        <div id="step-passkey" class="hidden">
            <div class="mb-4 text-center">
                <p class="text-gray-600 mb-1">Connexion en tant que</p>
                <p class="font-medium text-gray-900" id="display-email"></p>
                <button type="button" id="btn-change-email" class="text-sm text-indigo-600 hover:underline mt-1">
                    Changer
                </button>
            </div>

            <div class="mb-6">
                <label class="flex items-center justify-center">
                    <input type="checkbox" id="remember-passkey" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Se souvenir de moi</span>
                </label>
            </div>

            <button type="button" id="btn-login-passkey"
                class="w-full bg-gray-800 text-white py-2 px-4 rounded-md hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 flex items-center justify-center mb-4">
                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Connexion avec Passkey
            </button>

            <div class="text-center">
                <button type="button" id="btn-use-password" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                    Utiliser le mot de passe
                </button>
            </div>
        </div>

        <!-- Etape 2b: Mot de passe -->
        <form action="{{ route('admin.login') }}" method="POST" id="step-password" class="hidden">
            @csrf
            <input type="hidden" name="email" id="password-email">

            <div class="mb-4 text-center">
                <p class="text-gray-600 mb-1">Connexion en tant que</p>
                <p class="font-medium text-gray-900" id="display-email-password"></p>
                <button type="button" id="btn-change-email-password" class="text-sm text-indigo-600 hover:underline mt-1">
                    Changer
                </button>
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" id="password" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="remember" id="remember-password" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Se souvenir de moi</span>
                </label>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 mb-4">
                Connexion
            </button>

            <div class="text-center" id="back-to-passkey-container" style="display: none;">
                <button type="button" id="btn-back-to-passkey" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                    Utiliser une Passkey
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = '{{ csrf_token() }}';
            let currentEmail = '';
            let hasPasskey = false;

            // Elements
            const stepEmail = document.getElementById('step-email');
            const stepPasskey = document.getElementById('step-passkey');
            const stepPassword = document.getElementById('step-password');
            const emailInput = document.getElementById('email');
            const errorDiv = document.getElementById('error-message');

            // Buttons
            const btnContinue = document.getElementById('btn-continue');
            const btnLoginPasskey = document.getElementById('btn-login-passkey');
            const btnUsePassword = document.getElementById('btn-use-password');
            const btnChangeEmail = document.getElementById('btn-change-email');
            const btnChangeEmailPassword = document.getElementById('btn-change-email-password');
            const btnBackToPasskey = document.getElementById('btn-back-to-passkey');

            // Helpers
            function showError(message) {
                errorDiv.querySelector('p').textContent = message;
                errorDiv.classList.remove('hidden');
            }

            function hideError() {
                errorDiv.classList.add('hidden');
            }

            function showStep(step) {
                stepEmail.classList.add('hidden');
                stepPasskey.classList.add('hidden');
                stepPassword.classList.add('hidden');
                step.classList.remove('hidden');
            }

            function base64urlToBuffer(base64url) {
                const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
                const padding = '='.repeat((4 - base64.length % 4) % 4);
                const binary = atob(base64 + padding);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                return bytes.buffer;
            }

            function bufferToBase64url(buffer) {
                const bytes = new Uint8Array(buffer);
                let binary = '';
                for (let i = 0; i < bytes.length; i++) {
                    binary += String.fromCharCode(bytes[i]);
                }
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            }

            // Etape 1: Continuer apres email
            btnContinue.addEventListener('click', async () => {
                const email = emailInput.value.trim();
                if (!email) {
                    showError('Veuillez entrer votre email');
                    return;
                }

                hideError();
                btnContinue.disabled = true;
                btnContinue.textContent = 'Verification...';

                try {
                    const response = await fetch('{{ route('admin.check-email') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ email: email }),
                    });

                    if (response.status === 429) {
                        showError('Trop de tentatives. Reessayez dans quelques minutes.');
                        return;
                    }

                    if (!response.ok) {
                        showError('Erreur de verification');
                        return;
                    }

                    const data = await response.json();
                    currentEmail = email;
                    hasPasskey = data.has_passkey;

                    // Mettre a jour les affichages d'email
                    document.getElementById('display-email').textContent = email;
                    document.getElementById('display-email-password').textContent = email;
                    document.getElementById('password-email').value = email;

                    if (hasPasskey) {
                        document.getElementById('back-to-passkey-container').style.display = 'block';
                        showStep(stepPasskey);
                    } else {
                        document.getElementById('back-to-passkey-container').style.display = 'none';
                        showStep(stepPassword);
                        document.getElementById('password').focus();
                    }

                } catch (error) {
                    console.error('Error:', error);
                    showError('Erreur de connexion au serveur');
                } finally {
                    btnContinue.disabled = false;
                    btnContinue.textContent = 'Continuer';
                }
            });

            // Permettre Entree sur le champ email
            emailInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    btnContinue.click();
                }
            });

            // Changer d'email
            btnChangeEmail.addEventListener('click', () => {
                showStep(stepEmail);
                emailInput.focus();
            });

            btnChangeEmailPassword.addEventListener('click', () => {
                showStep(stepEmail);
                emailInput.focus();
            });

            // Utiliser mot de passe au lieu de passkey
            btnUsePassword.addEventListener('click', () => {
                showStep(stepPassword);
                document.getElementById('password').focus();
            });

            // Retour a passkey depuis mot de passe
            btnBackToPasskey.addEventListener('click', () => {
                showStep(stepPasskey);
            });

            // Login avec Passkey
            btnLoginPasskey.addEventListener('click', async () => {
                hideError();
                const passkeyButtonHtml = btnLoginPasskey.innerHTML;
                btnLoginPasskey.disabled = true;
                btnLoginPasskey.textContent = 'En attente de la cle...';

                try {
                    // 1. Recuperer les options d'authentification
                    const optionsResponse = await fetch('{{ route('admin.passkey.login-options') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ email: currentEmail }),
                    });

                    if (!optionsResponse.ok) {
                        const errorData = await optionsResponse.json().catch(() => ({}));
                        throw new Error(errorData.message || 'Erreur serveur');
                    }

                    const options = await optionsResponse.json();

                    // 2. Convertir les options pour l'API WebAuthn
                    const publicKeyOptions = {
                        challenge: base64urlToBuffer(options.challenge),
                        timeout: options.timeout,
                        rpId: options.rpId,
                        userVerification: options.userVerification || 'preferred',
                        allowCredentials: (options.allowCredentials || []).map(cred => ({
                            id: base64urlToBuffer(cred.id),
                            type: cred.type,
                            transports: cred.transports,
                        })),
                    };

                    // 3. Authentifier via l'API WebAuthn du navigateur
                    const credential = await navigator.credentials.get({
                        publicKey: publicKeyOptions,
                    });

                    // 4. Encoder la reponse pour l'envoyer au serveur
                    const assertionResponse = {
                        id: credential.id,
                        rawId: bufferToBase64url(credential.rawId),
                        type: credential.type,
                        response: {
                            clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                            authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                            signature: bufferToBase64url(credential.response.signature),
                            userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null,
                        },
                        remember: document.getElementById('remember-passkey').checked,
                    };

                    // 5. Envoyer au serveur pour verification
                    const loginResponse = await fetch('{{ route('admin.passkey.login') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(assertionResponse),
                    });

                    if (!loginResponse.ok) {
                        const errorData = await loginResponse.json().catch(() => ({}));
                        throw new Error(errorData.error || 'Authentification echouee');
                    }

                    const result = await loginResponse.json();

                    if (result.success && result.redirect) {
                        window.location.href = result.redirect;
                    } else if (result.success) {
                        window.location.href = '{{ route('admin.dashboard') }}';
                    } else {
                        throw new Error(result.error || 'Authentification echouee');
                    }

                } catch (error) {
                    console.error('WebAuthn error:', error);
                    let message = 'Une erreur est survenue';

                    if (error.name === 'NotAllowedError') {
                        message = 'Operation annulee ou refusee.';
                    } else if (error.message) {
                        message = error.message;
                    }

                    showError(message);
                } finally {
                    btnLoginPasskey.disabled = false;
                    btnLoginPasskey.innerHTML = passkeyButtonHtml;
                }
            });
        });
    </script>
</body>
</html>
