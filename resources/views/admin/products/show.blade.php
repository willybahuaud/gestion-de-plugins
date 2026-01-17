<x-admin-layout title="{{ $product->name }}">
    <div class="mb-6">
        <a href="{{ route('admin.products.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux produits</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
                <p class="text-gray-500 font-mono">{{ $product->slug }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="px-3 py-1 rounded-full {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                    {{ $product->is_active ? 'Actif' : 'Inactif' }}
                </span>
                <a href="{{ route('admin.products.edit', $product) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Modifier
                </a>
            </div>
        </div>

        @if($product->description)
            <div class="mt-4">
                <h3 class="text-sm font-medium text-gray-700">Description</h3>
                <p class="mt-1 text-gray-600">{{ $product->description }}</p>
            </div>
        @endif

        <div class="mt-6 grid grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Total licences</p>
                <p class="text-2xl font-bold text-gray-900">{{ $licensesCount }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Licences actives</p>
                <p class="text-2xl font-bold text-green-600">{{ $activeLicenses }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Releases</p>
                <p class="text-2xl font-bold text-gray-900">{{ $product->releases->count() }}</p>
            </div>
        </div>

        @if($product->stripe_product_id)
            <div class="mt-4">
                <p class="text-sm text-gray-500">Stripe Product ID: <code class="font-mono">{{ $product->stripe_product_id }}</code></p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Prix</h2>
                <a href="{{ route('admin.prices.create', $product) }}" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded-md hover:bg-indigo-700">
                    + Ajouter
                </a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($product->prices as $price)
                    <a href="{{ route('admin.prices.edit', [$product, $price]) }}" class="block px-6 py-4 hover:bg-gray-50">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium">{{ $price->name }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $price->formatted_amount }}
                                    @if($price->isRecurring())
                                        / {{ $price->interval === 'year' ? 'an' : 'mois' }}
                                    @else
                                        (lifetime)
                                    @endif
                                    - {{ $price->max_activations == 0 ? 'Sites illimites' : $price->max_activations . ' site(s)' }}
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $price->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $price->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-4 text-gray-500">
                        Aucun prix configure.
                        <a href="{{ route('admin.prices.create', $product) }}" class="text-indigo-600 hover:underline">Ajouter un prix</a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Dernieres releases</h2>
                <a href="{{ route('admin.releases.create', $product) }}" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded-md hover:bg-indigo-700">
                    + Ajouter
                </a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($product->releases as $release)
                    <a href="{{ route('admin.releases.show', [$product, $release]) }}" class="block px-6 py-4 hover:bg-gray-50">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium font-mono">v{{ $release->version }}</p>
                                <p class="text-sm text-gray-500">{{ $release->published_at?->format('d/m/Y') ?? 'Non publiee' }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $release->isPublished() ? 'bg-green-100 text-green-800' : ($release->isScheduled() ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                {{ $release->isPublished() ? 'Publiee' : ($release->isScheduled() ? 'Planifiee' : 'Brouillon') }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="px-6 py-4 text-gray-500">
                        Aucune release.
                        <a href="{{ route('admin.releases.create', $product) }}" class="text-indigo-600 hover:underline">Creer la premiere</a>
                    </div>
                @endforelse
            </div>
            @if($product->releases->count() > 0)
                <div class="px-6 py-3 border-t border-gray-200 bg-gray-50">
                    <a href="{{ route('admin.releases.index', $product) }}" class="text-sm text-indigo-600 hover:underline">Voir toutes les releases &rarr;</a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
