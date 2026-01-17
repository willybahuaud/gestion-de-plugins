<x-admin-layout title="Modifier v{{ $release->version }} - {{ $product->name }}">
    <div class="mb-6">
        <a href="{{ route('admin.releases.show', [$product, $release]) }}" class="text-indigo-600 hover:underline">&larr; Retour a la release</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Modifier la release v{{ $release->version }}</h1>

        <form action="{{ route('admin.releases.update', [$product, $release]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600 font-mono">
                            v{{ $release->version }}
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Non modifiable apres creation</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fichier</label>
                        <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                            {{ $release->formatted_file_size }}
                            <a href="{{ route('admin.releases.download', [$product, $release]) }}" class="text-indigo-600 hover:underline ml-2">Telecharger</a>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Pour changer le fichier, creez une nouvelle release</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="min_php_version" class="block text-sm font-medium text-gray-700 mb-1">Version PHP min.</label>
                            <input type="text" name="min_php_version" id="min_php_version" value="{{ old('min_php_version', $release->min_php_version) }}"
                                placeholder="8.0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="min_wp_version" class="block text-sm font-medium text-gray-700 mb-1">Version WP min.</label>
                            <input type="text" name="min_wp_version" id="min_wp_version" value="{{ old('min_wp_version', $release->min_wp_version) }}"
                                placeholder="6.0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-4">
                        <label for="changelog" class="block text-sm font-medium text-gray-700 mb-1">Changelog (Markdown)</label>
                        <textarea name="changelog" id="changelog" rows="8"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm @error('changelog') border-red-500 @enderror">{{ old('changelog', $release->changelog) }}</textarea>
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
                        <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $release->is_published) ? 'checked' : '' }}
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-700">Publier cette release</span>
                    </label>
                </div>

                <div class="mb-4" id="published-at-group" style="display: none;">
                    <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Date de publication</label>
                    <input type="datetime-local" name="published_at" id="published_at"
                        value="{{ old('published_at', $release->published_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <p class="mt-1 text-sm text-gray-500">Laisser vide pour publier immediatement</p>
                </div>

                @if($release->isPublished())
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <p class="text-sm text-green-800">
                            Cette release est publiee depuis le {{ $release->published_at->format('d/m/Y a H:i') }}.
                        </p>
                    </div>
                @elseif($release->isScheduled())
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <p class="text-sm text-yellow-800">
                            Cette release est planifiee pour le {{ $release->published_at->format('d/m/Y a H:i') }}.
                        </p>
                    </div>
                @endif
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                    Enregistrer
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
