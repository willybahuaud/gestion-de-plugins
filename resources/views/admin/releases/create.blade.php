<x-admin-layout title="Nouvelle release - {{ $product->name }}">
    <div class="mb-6">
        <a href="{{ route('admin.releases.index', $product) }}" class="text-indigo-600 hover:underline">&larr; Retour aux releases</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Nouvelle release pour {{ $product->name }}</h1>

        <form action="{{ route('admin.releases.store', $product) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label for="version" class="block text-sm font-medium text-gray-700 mb-1">Version (semver)</label>
                        <input type="text" name="version" id="version" value="{{ old('version') }}" required
                            placeholder="1.0.0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono @error('version') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">Format: X.Y.Z (ex: 1.0.0, 2.1.3-beta)</p>
                        @error('version')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="file" class="block text-sm font-medium text-gray-700 mb-1">Fichier ZIP</label>
                        <input type="file" name="file" id="file" required accept=".zip"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('file') border-red-500 @enderror">
                        <p class="mt-1 text-sm text-gray-500">Max 50 Mo</p>
                        @error('file')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="min_php_version" class="block text-sm font-medium text-gray-700 mb-1">Version PHP min.</label>
                            <input type="text" name="min_php_version" id="min_php_version" value="{{ old('min_php_version', '8.0') }}"
                                placeholder="8.0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="min_wp_version" class="block text-sm font-medium text-gray-700 mb-1">Version WP min.</label>
                            <input type="text" name="min_wp_version" id="min_wp_version" value="{{ old('min_wp_version', '6.0') }}"
                                placeholder="6.0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <label for="changelog" class="block text-sm font-medium text-gray-700 mb-1">Changelog (Markdown)</label>
                        <textarea name="changelog" id="changelog" rows="8"
                            placeholder="## Nouveautes&#10;- Fonctionnalite X&#10;- Fonctionnalite Y&#10;&#10;## Corrections&#10;- Bug Z corrige"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm @error('changelog') border-red-500 @enderror">{{ old('changelog') }}</textarea>
                        @error('changelog')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6 mt-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Publication</h3>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Publier cette release</span>
                    </label>
                </div>

                <div class="mb-4" id="published-at-group" style="display: none;">
                    <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Date de publication</label>
                    <input type="datetime-local" name="published_at" id="published_at" value="{{ old('published_at') }}"
                        class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Laisser vide pour publier immediatement</p>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Creer la release
                </button>
            </div>
        </form>
    </div>

    <script>
        const isPublishedCheckbox = document.getElementById('is_published');
        const publishedAtGroup = document.getElementById('published-at-group');

        function togglePublishedAt() {
            publishedAtGroup.style.display = isPublishedCheckbox.checked ? 'block' : 'none';
        }

        isPublishedCheckbox.addEventListener('change', togglePublishedAt);
        togglePublishedAt();
    </script>
</x-admin-layout>
