<x-admin-layout title="Nouveau token API">
    <div class="mb-6">
        <a href="{{ route('admin.api-tokens.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux tokens</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Nouveau token API</h1>

        <form action="{{ route('admin.api-tokens.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom (site WordPress)</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration (optionnel)</label>
                <input type="date" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('expires_at') border-red-500 @enderror">
                <p class="mt-1 text-sm text-gray-500">Laissez vide pour un token sans expiration</p>
                @error('expires_at')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Creer le token
            </button>
        </form>
    </div>
</x-admin-layout>
