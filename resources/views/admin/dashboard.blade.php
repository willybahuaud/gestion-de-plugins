<x-admin-layout title="Dashboard">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Clients</h3>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['users_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Produits</h3>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['products_count']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Licences actives</h3>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['licenses_active']) }}</p>
            <p class="text-sm text-gray-500">sur {{ number_format($stats['licenses_total']) }} total</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-gray-500 text-sm font-medium">Revenus (mois)</h3>
            <p class="text-3xl font-bold text-green-600">{{ number_format($stats['revenue_month'], 2, ',', ' ') }} EUR</p>
            <p class="text-sm text-gray-500">{{ number_format($stats['revenue_year'], 2, ',', ' ') }} EUR cette annee</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Dernieres licences</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentLicenses as $license)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:underline font-mono text-sm">
                                    {{ Str::limit($license->uuid, 16) }}
                                </a>
                                <p class="text-sm text-gray-500">
                                    {{ $license->user?->email ?? 'N/A' }} - {{ $license->product?->name ?? 'N/A' }}
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $license->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $license->status }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-gray-500">Aucune licence</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Derniers clients</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentUsers as $user)
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="{{ route('admin.users.show', $user) }}" class="text-indigo-600 hover:underline">
                                    {{ $user->email }}
                                </a>
                                <p class="text-sm text-gray-500">{{ $user->name ?? 'Sans nom' }}</p>
                            </div>
                            <span class="text-sm text-gray-500">{{ $user->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-gray-500">Aucun client</div>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
