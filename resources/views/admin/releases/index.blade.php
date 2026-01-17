<x-admin-layout title="Releases - {{ $product->name }}">
    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('admin.products.show', $product) }}" class="text-indigo-600 hover:underline">&larr; Retour au produit</a>
        <a href="{{ route('admin.releases.create', $product) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            + Nouvelle release
        </a>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-bold text-gray-900">Releases de {{ $product->name }}</h1>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Taille</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date publication</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($releases as $release)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('admin.releases.show', [$product, $release]) }}" class="font-mono font-medium text-indigo-600 hover:underline">
                                    v{{ $release->version }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $release->formatted_file_size }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($release->isPublished())
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Publiee</span>
                                @elseif($release->isScheduled())
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Planifiee</span>
                                @else
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Brouillon</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $release->published_at?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.releases.edit', [$product, $release]) }}" class="text-indigo-600 hover:underline mr-3">Modifier</a>
                                <a href="{{ route('admin.releases.download', [$product, $release]) }}" class="text-gray-600 hover:underline">Telecharger</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                Aucune release.
                                <a href="{{ route('admin.releases.create', $product) }}" class="text-indigo-600 hover:underline">Creer la premiere</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($releases->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $releases->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
