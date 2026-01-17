<x-admin-layout title="Modifier le prix - {{ $product->name }}">
    <div class="mb-6">
        <a href="{{ route('admin.products.show', $product) }}" class="text-indigo-600 hover:underline">&larr; Retour au produit</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Modifier le prix</h1>

        <form action="{{ route('admin.prices.update', [$product, $price]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom du tarif</label>
                <input type="text" name="name" id="name" value="{{ old('name', $price->name) }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Type de tarification</label>
                <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                    {{ $price->type === 'recurring' ? 'Abonnement' : 'Paiement unique (lifetime)' }}
                    @if($price->type === 'recurring' && $price->interval)
                        ({{ $price->interval === 'year' ? 'Annuel' : 'Mensuel' }})
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500">Non modifiable (contrainte Stripe)</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix</label>
                <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                    {{ $price->formatted_amount }}
                </div>
                <p class="mt-1 text-sm text-gray-500">Non modifiable (contrainte Stripe)</p>
            </div>

            <div class="mb-4">
                <label for="max_activations" class="block text-sm font-medium text-gray-700 mb-1">Nombre max d'activations</label>
                <input type="number" name="max_activations" id="max_activations" value="{{ old('max_activations', $price->max_activations) }}" required
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('max_activations') border-red-500 @enderror">
                <p class="mt-1 text-sm text-gray-500">0 = illimite</p>
                @error('max_activations')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $price->is_active) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Prix actif</span>
                </label>
            </div>

            @if($price->stripe_price_id)
                <div class="mb-6">
                    <p class="text-sm text-gray-500">Stripe Price ID: <code class="font-mono bg-gray-100 px-1 rounded">{{ $price->stripe_price_id }}</code></p>
                </div>
            @endif

            <div class="flex space-x-4">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Enregistrer
                </button>

                @if(!$price->licenses()->exists())
                    <button type="button" onclick="confirmDelete()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                        Supprimer
                    </button>
                @endif
            </div>
        </form>

        @if(!$price->licenses()->exists())
            <form id="delete-form" action="{{ route('admin.prices.destroy', [$product, $price]) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <script>
        function confirmDelete() {
            if (confirm('Etes-vous sur de vouloir supprimer ce prix ? Cette action est irreversible.')) {
                document.getElementById('delete-form').submit();
            }
        }
    </script>
</x-admin-layout>
