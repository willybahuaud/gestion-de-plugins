<x-admin-layout title="Modifier licence {{ Str::limit($license->uuid, 16) }}">
    <div class="mb-6">
        <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:underline">&larr; Retour aux details</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Modifier la licence</h1>
        <p class="text-gray-500 font-mono mb-6">{{ $license->uuid }}</p>

        <div class="mb-6 p-4 bg-gray-50 rounded-md">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500">Client :</span>
                    <span class="font-medium">{{ $license->user?->email ?? 'N/A' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Produit :</span>
                    <span class="font-medium">{{ $license->product?->name ?? 'N/A' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Type :</span>
                    <span class="font-medium">{{ $license->type }}</span>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.licenses.update', $license) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                        <select name="status" id="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                            <option value="active" {{ old('status', $license->status) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ old('status', $license->status) == 'suspended' ? 'selected' : '' }}>Suspendue</option>
                            <option value="expired" {{ old('status', $license->status) == 'expired' ? 'selected' : '' }}>Expiree</option>
                            <option value="revoked" {{ old('status', $license->status) == 'revoked' ? 'selected' : '' }}>Revoquee</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="activations_limit" class="block text-sm font-medium text-gray-700 mb-1">Nombre d'activations max</label>
                        <input type="number" name="activations_limit" id="activations_limit" value="{{ old('activations_limit', $license->activations_limit) }}" required min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('activations_limit') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">
                            Actuellement : {{ $license->activations->where('is_active', true)->count() }} activation(s) active(s)
                        </p>
                        @error('activations_limit')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration</label>
                        <input type="date" name="expires_at" id="expires_at" value="{{ old('expires_at', $license->expires_at?->format('Y-m-d')) }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('expires_at') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">Laisser vide pour une licence sans expiration</p>
                        @error('expires_at')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($license->stripe_subscription_id)
                        <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-800">
                                <strong>Abonnement Stripe lie :</strong><br>
                                <code class="font-mono text-xs">{{ $license->stripe_subscription_id }}</code>
                            </p>
                            <p class="mt-2 text-xs text-blue-600">
                                Les modifications de statut et d'expiration doivent etre coherentes avec l'abonnement Stripe.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Enregistrer les modifications
                </button>

                <a href="{{ route('admin.licenses.show', $license) }}" class="text-gray-600 hover:underline">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</x-admin-layout>
