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
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Prix</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($product->prices as $price)
                    <div class="px-6 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium">{{ $price->name }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $price->formatted_amount }}
                                    @if($price->isRecurring())
                                        / {{ $price->interval_count > 1 ? $price->interval_count . ' ' : '' }}{{ $price->interval }}(s)
                                    @else
                                        (lifetime)
                                    @endif
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $price->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $price->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-gray-500">Aucun prix configure</div>
                @endforelse
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Dernieres releases</h2>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($product->releases as $release)
                    <div class="px-6 py-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium font-mono">v{{ $release->version }}</p>
                                <p class="text-sm text-gray-500">{{ $release->published_at?->format('d/m/Y') ?? 'Non publiee' }}</p>
                            </div>
                            <span class="px-2 py-1 text-xs rounded-full {{ $release->isPublished() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $release->isPublished() ? 'Publiee' : ($release->isScheduled() ? 'Planifiee' : 'Brouillon') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-4 text-gray-500">Aucune release</div>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
