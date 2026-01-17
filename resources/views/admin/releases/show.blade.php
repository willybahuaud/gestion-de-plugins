<x-admin-layout title="v{{ $release->version }} - {{ $product->name }}">
    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('admin.releases.index', $product) }}" class="text-indigo-600 hover:underline">&larr; Retour aux releases</a>
        <div class="flex space-x-3">
            <a href="{{ route('admin.releases.download', [$product, $release]) }}" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                Telecharger
            </a>
            <a href="{{ route('admin.releases.edit', [$product, $release]) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Modifier
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 font-mono">v{{ $release->version }}</h1>
                <p class="text-gray-500">{{ $product->name }}</p>
            </div>
            <div>
                @if($release->isPublished())
                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-800">Publiee</span>
                @elseif($release->isScheduled())
                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800">Planifiee pour {{ $release->published_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800">Brouillon</span>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Taille</p>
                <p class="text-lg font-medium text-gray-900">{{ $release->formatted_file_size }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">PHP minimum</p>
                <p class="text-lg font-medium text-gray-900">{{ $release->min_php_version ?? 'Non specifie' }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">WordPress minimum</p>
                <p class="text-lg font-medium text-gray-900">{{ $release->min_wp_version ?? 'Non specifie' }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Date de publication</p>
                <p class="text-lg font-medium text-gray-900">{{ $release->published_at?->format('d/m/Y H:i') ?? '-' }}</p>
            </div>
        </div>

        @if($release->file_hash)
            <div class="mt-4">
                <p class="text-sm text-gray-500">SHA256: <code class="font-mono bg-gray-100 px-2 py-1 rounded text-xs break-all">{{ $release->file_hash }}</code></p>
            </div>
        @endif

        <div class="mt-4">
            <p class="text-sm text-gray-500">Chemin fichier: <code class="font-mono bg-gray-100 px-2 py-1 rounded text-xs">{{ $release->file_path }}</code></p>
        </div>
    </div>

    @if($release->changelog)
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Changelog</h2>
            <div class="prose prose-sm max-w-none">
                {!! \Illuminate\Support\Str::markdown($release->changelog) !!}
            </div>
        </div>
    @endif

    <div class="mt-6 flex justify-end">
        <form action="{{ route('admin.releases.destroy', [$product, $release]) }}" method="POST"
            onsubmit="return confirm('Etes-vous sur de vouloir supprimer cette release ? Le fichier sera egalement supprime.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 hover:underline">
                Supprimer cette release
            </button>
        </form>
    </div>
</x-admin-layout>
