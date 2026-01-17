<x-admin-layout title="Nouveau prix - {{ $product->name }}">
    <div class="mb-6">
        <a href="{{ route('admin.products.show', $product) }}" class="text-indigo-600 hover:underline">&larr; Retour au produit</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Nouveau prix pour {{ $product->name }}</h1>

        <form action="{{ route('admin.prices.store', $product) }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom du tarif</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    placeholder="Ex: Licence annuelle, Lifetime, etc."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type de tarification</label>
                <select name="type" id="type" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                    <option value="recurring" {{ old('type') === 'recurring' ? 'selected' : '' }}>Abonnement (recurring)</option>
                    <option value="one_time" {{ old('type') === 'one_time' ? 'selected' : '' }}>Paiement unique (lifetime)</option>
                </select>
                @error('type')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Prix (EUR)</label>
                <div class="relative">
                    <input type="number" name="amount" id="amount" value="{{ old('amount') }}" required
                        step="0.01" min="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-12 @error('amount') border-red-500 @enderror">
                    <span class="absolute right-3 top-2 text-gray-500">EUR</span>
                </div>
                @error('amount')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4" id="interval-group">
                <label for="interval" class="block text-sm font-medium text-gray-700 mb-1">Intervalle de facturation</label>
                <select name="interval" id="interval"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('interval') border-red-500 @enderror">
                    <option value="year" {{ old('interval', 'year') === 'year' ? 'selected' : '' }}>Annuel</option>
                    <option value="month" {{ old('interval') === 'month' ? 'selected' : '' }}>Mensuel</option>
                </select>
                @error('interval')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="max_activations" class="block text-sm font-medium text-gray-700 mb-1">Nombre max d'activations</label>
                <input type="number" name="max_activations" id="max_activations" value="{{ old('max_activations', 1) }}" required
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('max_activations') border-red-500 @enderror">
                <p class="mt-1 text-sm text-gray-500">0 = illimite</p>
                @error('max_activations')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Prix actif</span>
                </label>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                <p class="text-sm text-yellow-800">
                    <strong>Note :</strong> Une fois cree, le montant et le type ne pourront plus etre modifies (contrainte Stripe).
                    Si vous avez besoin de changer ces valeurs, vous devrez creer un nouveau prix.
                </p>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Creer le prix
            </button>
        </form>
    </div>

    <script>
        document.getElementById('type').addEventListener('change', function() {
            const intervalGroup = document.getElementById('interval-group');
            if (this.value === 'one_time') {
                intervalGroup.style.display = 'none';
            } else {
                intervalGroup.style.display = 'block';
            }
        });
        // Trigger on load
        document.getElementById('type').dispatchEvent(new Event('change'));
    </script>
</x-admin-layout>
