<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="admin-email" content="{{ Auth::guard('admin')->user()->email }}">
    <title>{{ $title ?? 'Admin' }} - Plugin Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-indigo-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('admin.dashboard') }}" class="font-bold text-xl">Plugin Hub</a>
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="{{ route('admin.dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-700' : '' }}">Dashboard</a>
                        <a href="{{ route('admin.products.index') }}" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 {{ request()->routeIs('admin.products.*') ? 'bg-indigo-700' : '' }}">Produits</a>
                        <a href="{{ route('admin.users.index') }}" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 {{ request()->routeIs('admin.users.*') ? 'bg-indigo-700' : '' }}">Clients</a>
                        <a href="{{ route('admin.licenses.index') }}" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 {{ request()->routeIs('admin.licenses.*') ? 'bg-indigo-700' : '' }}">Licences</a>
                        <a href="{{ route('admin.api-tokens.index') }}" class="px-3 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 {{ request()->routeIs('admin.api-tokens.*') ? 'bg-indigo-700' : '' }}">API Tokens</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('admin.passkeys.index') }}" class="text-sm hover:underline flex items-center" title="Passkeys">
                        <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </a>
                    <a href="{{ route('admin.profile.edit') }}" class="text-sm hover:underline">{{ Auth::guard('admin')->user()->name }}</a>
                    <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-sm hover:underline">Deconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if(session('plain_token'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <p class="font-bold">Token API (copiez-le maintenant, il ne sera plus affiche) :</p>
                <code class="block mt-2 p-2 bg-yellow-50 rounded font-mono text-sm break-all">{{ session('plain_token') }}</code>
            </div>
        @endif

        {{ $slot }}
    </main>

    <!-- Modal Session Expiree -->
    <div id="session-expired-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="mb-6 flex flex-col items-center text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-gray-900">Session expiree</h2>
                <p class="text-gray-600">Votre session a expire. Reconnectez-vous pour continuer.</p>
            </div>

            <div id="session-error" class="hidden mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-600"></div>

            <div class="flex flex-col gap-3">
                <button type="button" id="btn-reconnect"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-800 px-4 py-3 font-medium text-white transition-colors hover:bg-gray-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    <span>Se reconnecter</span>
                </button>

                <a href="{{ route('admin.login') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-3 font-medium text-gray-700 transition-colors hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Page de connexion
                </a>
            </div>

            <p class="mt-4 text-center text-xs text-gray-500">
                Utilisez votre cle de securite, Touch ID ou Face ID
            </p>
        </div>
    </div>

    <script>
    (function() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const adminEmail = document.querySelector('meta[name="admin-email"]').content;
        const modal = document.getElementById('session-expired-modal');
        const errorDiv = document.getElementById('session-error');
        const btnReconnect = document.getElementById('btn-reconnect');
        let isModalShown = false;

        // Helpers WebAuthn
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

        function showModal() {
            if (isModalShown) return;
            isModalShown = true;
            modal.classList.remove('hidden');
            errorDiv.classList.add('hidden');
        }

        function hideModal() {
            isModalShown = false;
            modal.classList.add('hidden');
        }

        function showError(message) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
        }

        // Intercepter les erreurs 401 sur fetch
        const originalFetch = window.fetch;
        window.fetch = async function(...args) {
            const response = await originalFetch.apply(this, args);
            if (response.status === 401) {
                const url = typeof args[0] === 'string' ? args[0] : args[0].url;
                // Ne pas intercepter les routes d'auth
                if (!url.includes('/login') && !url.includes('/passkey')) {
                    showModal();
                }
            }
            return response;
        };

        // Reconnexion avec passkey
        btnReconnect.addEventListener('click', async () => {
            const btnHtml = btnReconnect.innerHTML;
            btnReconnect.disabled = true;
            btnReconnect.innerHTML = '<svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Reconnexion...</span>';
            errorDiv.classList.add('hidden');

            try {
                // 1. Options passkey
                const optionsResponse = await originalFetch('{{ route('admin.passkey.login-options') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email: adminEmail }),
                });

                if (!optionsResponse.ok) {
                    throw new Error('Passkey non disponible');
                }

                const options = await optionsResponse.json();

                // 2. WebAuthn
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

                const credential = await navigator.credentials.get({ publicKey: publicKeyOptions });

                // 3. Envoyer au serveur
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
                    remember: false,
                };

                const loginResponse = await originalFetch('{{ route('admin.passkey.login') }}', {
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
                    throw new Error('Authentification echouee');
                }

                // Succes - recharger la page
                hideModal();
                window.location.reload();

            } catch (error) {
                let message = 'Erreur de reconnexion';
                if (error.name === 'NotAllowedError') {
                    message = 'Operation annulee';
                } else if (error.message) {
                    message = error.message;
                }
                showError(message);
            } finally {
                btnReconnect.disabled = false;
                btnReconnect.innerHTML = btnHtml;
            }
        });
    })();
    </script>
</body>
</html>
