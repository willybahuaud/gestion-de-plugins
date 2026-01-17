<x-admin-layout title="Dashboard">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>

    @if($alerts['suspended_licenses']->count() > 0 || $alerts['expiring_soon']->count() > 0)
        <div class="mb-6 space-y-4">
            @if($alerts['suspended_licenses']->count() > 0)
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-red-800">Paiements echoues ({{ $alerts['suspended_licenses']->count() }})</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($alerts['suspended_licenses'] as $license)
                                        <li>
                                            <a href="{{ route('admin.licenses.show', $license) }}" class="underline hover:no-underline">
                                                {{ $license->user?->email ?? 'N/A' }}
                                            </a>
                                            - {{ $license->product?->name ?? 'N/A' }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($alerts['expiring_soon']->count() > 0)
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800">Licences expirant bientot ({{ $alerts['expiring_soon']->count() }})</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($alerts['expiring_soon'] as $license)
                                        <li>
                                            <a href="{{ route('admin.licenses.show', $license) }}" class="underline hover:no-underline">
                                                {{ $license->user?->email ?? 'N/A' }}
                                            </a>
                                            - {{ $license->product?->name ?? 'N/A' }}
                                            <span class="text-yellow-600">(expire {{ $license->expires_at->diffForHumans() }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

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
