@props(['title' => 'Admin'])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - Plugin Hub</title>
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
                    <a href="{{ route('admin.profile.edit') }}" class="text-sm hover:underline {{ request()->routeIs('admin.profile.*') ? 'underline' : '' }}">
                        {{ Auth::guard('admin')->user()->name }}
                    </a>
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
</body>
</html>
