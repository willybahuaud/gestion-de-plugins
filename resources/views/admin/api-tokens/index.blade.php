<x-admin-layout title="API Tokens">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">API Tokens</h1>
        <a href="{{ route('admin.api-tokens.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Nouveau token
        </a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Derniere utilisation</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expiration</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($tokens as $token)
                    <tr class="{{ $token->isExpired() ? 'bg-red-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $token->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if(in_array('*', $token->abilities ?? []))
                                <span class="text-green-600">Toutes</span>
                            @else
                                {{ implode(', ', $token->abilities ?? []) }}
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $token->last_used_at?->diffForHumans() ?? 'Jamais' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($token->expires_at)
                                <span class="{{ $token->isExpired() ? 'text-red-600' : 'text-gray-500' }}">
                                    {{ $token->expires_at->format('d/m/Y') }}
                                    @if($token->isExpired()) (expire) @endif
                                </span>
                            @else
                                <span class="text-gray-500">Jamais</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <form action="{{ route('admin.api-tokens.destroy', $token) }}" method="POST" onsubmit="return confirm('Supprimer ce token ?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">Aucun token</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tokens->links() }}
    </div>
</x-admin-layout>
