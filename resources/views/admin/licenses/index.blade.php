<x-admin-layout title="Licences">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Licences</h1>
        <a href="{{ route('admin.licenses.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Nouvelle licence
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form action="{{ route('admin.licenses.index') }}" method="GET" class="flex flex-wrap gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher (UUID, email)"
                class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les statuts</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspendue</option>
                <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expiree</option>
                <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoquee</option>
            </select>
            <select name="product_id" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les produits</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">Filtrer</button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Licence</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Expire</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($licenses as $license)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:underline font-mono text-sm">
                                {{ Str::limit($license->uuid, 16) }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $license->user?->email ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $license->product?->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $license->type }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $license->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $license->status === 'suspended' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $license->status === 'expired' ? 'bg-gray-100 text-gray-800' : '' }}
                                {{ $license->status === 'revoked' ? 'bg-red-100 text-red-800' : '' }}
                            ">
                                {{ $license->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $license->expires_at?->format('d/m/Y') ?? 'Jamais' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Aucune licence</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $licenses->withQueryString()->links() }}
    </div>
</x-admin-layout>
