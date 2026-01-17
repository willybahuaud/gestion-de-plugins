<x-admin-layout title="Nouvelle licence">
    <div class="mb-6">
        <a href="{{ route('admin.licenses.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux licences</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Creer une licence manuellement</h1>

        <form action="{{ route('admin.licenses.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                        <select name="user_id" id="user_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('user_id') border-red-500 @enderror">
                            <option value="">Selectionner un client</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->email }} {{ $user->name ? '(' . $user->name . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">Produit</label>
                        <select name="product_id" id="product_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('product_id') border-red-500 @enderror">
                            <option value="">Selectionner un produit</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-prices="{{ $product->prices->toJson() }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="price_id" class="block text-sm font-medium text-gray-700 mb-1">Prix (optionnel)</label>
                        <select name="price_id" id="price_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('price_id') border-red-500 @enderror">
                            <option value="">Aucun (licence manuelle)</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Associer a un prix pour la coherence des stats</p>
                        @error('price_id')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type de licence</label>
                        <select name="type" id="type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('type') border-red-500 @enderror">
                            <option value="lifetime" {{ old('type') == 'lifetime' ? 'selected' : '' }}>Lifetime (a vie)</option>
                            <option value="subscription" {{ old('type') == 'subscription' ? 'selected' : '' }}>Subscription (abonnement)</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="activations_limit" class="block text-sm font-medium text-gray-700 mb-1">Nombre d'activations max</label>
                        <input type="number" name="activations_limit" id="activations_limit" value="{{ old('activations_limit', 1) }}" required min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('activations_limit') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">Nombre de sites WordPress autorises</p>
                        @error('activations_limit')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration (optionnel)</label>
                        <input type="date" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('expires_at') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">Laisser vide pour une licence sans expiration</p>
                        @error('expires_at')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-800">
                    <strong>Note :</strong> Cette fonction permet de creer des licences manuellement (ex: offrir une licence, migration d'un ancien systeme).
                    Pour les achats normaux, les licences sont creees automatiquement via Stripe.
                </p>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Creer la licence
                </button>
            </div>
        </form>
    </div>

    <script>
        const productSelect = document.getElementById('product_id');
        const priceSelect = document.getElementById('price_id');
        const typeSelect = document.getElementById('type');
        const activationsInput = document.getElementById('activations_limit');

        function updatePrices() {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const pricesJson = selectedOption.getAttribute('data-prices');

            priceSelect.innerHTML = '<option value="">Aucun (licence manuelle)</option>';

            if (pricesJson) {
                const prices = JSON.parse(pricesJson);
                prices.forEach(price => {
                    if (price.is_active) {
                        const option = document.createElement('option');
                        option.value = price.id;
                        const amount = (price.amount / 100).toFixed(2) + ' EUR';
                        const interval = price.type === 'recurring'
                            ? (price.interval === 'year' ? '/an' : '/mois')
                            : ' (lifetime)';
                        option.textContent = price.name + ' - ' + amount + interval + ' - ' + price.max_activations + ' site(s)';
                        option.dataset.type = price.type === 'recurring' ? 'subscription' : 'lifetime';
                        option.dataset.activations = price.max_activations;
                        priceSelect.appendChild(option);
                    }
                });
            }
        }

        function updateFromPrice() {
            const selectedOption = priceSelect.options[priceSelect.selectedIndex];
            if (selectedOption.value && selectedOption.dataset.type) {
                typeSelect.value = selectedOption.dataset.type;
                activationsInput.value = selectedOption.dataset.activations || 1;
            }
        }

        productSelect.addEventListener('change', updatePrices);
        priceSelect.addEventListener('change', updateFromPrice);

        // Init
        updatePrices();
    </script>
</x-admin-layout>
