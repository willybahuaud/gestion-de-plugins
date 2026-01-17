<x-admin-layout title="Licence {{ Str::limit($license->uuid, 16) }}">
    <div class="mb-6">
        <a href="{{ route('admin.licenses.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux licences</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $license->uuid }}</h1>
                <p class="text-gray-500">Creee le {{ $license->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="px-3 py-1 rounded-full
                    {{ $license->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $license->status === 'suspended' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $license->status === 'expired' ? 'bg-gray-100 text-gray-800' : '' }}
                    {{ $license->status === 'revoked' ? 'bg-red-100 text-red-800' : '' }}
                ">
                    {{ $license->status }}
                </span>
                @if($license->status === 'active')
                    <form action="{{ route('admin.licenses.revoke', $license) }}" method="POST" onsubmit="return confirm('Revoquer cette licence ?')">
                        @csrf
                        <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Revoquer</button>
                    </form>
                @else
                    <form action="{{ route('admin.licenses.reactivate', $license) }}" method="POST">
                        @csrf
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Reactiver</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Client</p>
                <a href="{{ route('admin.users.show', $license->user) }}" class="text-indigo-600 hover:underline">{{ $license->user?->email ?? 'N/A' }}</a>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Produit</p>
                <a href="{{ route('admin.products.show', $license->product) }}" class="text-indigo-600 hover:underline">{{ $license->product?->name ?? 'N/A' }}</a>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Type</p>
                <p class="font-medium">{{ $license->type }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Expiration</p>
                <p class="font-medium">{{ $license->expires_at?->format('d/m/Y') ?? 'Jamais' }}</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Activations</p>
                <p class="font-medium">{{ $license->activations->where('is_active', true)->count() }} / {{ $license->activations_limit }}</p>
            </div>
            @if($license->stripe_subscription_id)
                <div class="bg-gray-50 p-4 rounded">
                    <p class="text-sm text-gray-500">Stripe Subscription</p>
                    <code class="font-mono text-sm">{{ $license->stripe_subscription_id }}</code>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Activations</h2>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($license->activations as $activation)
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $activation->domain }}</p>
                            <p class="text-sm text-gray-500">
                                IP: {{ $activation->ip_address }}
                                @if($activation->local_ip)
                                    (local: {{ $activation->local_ip }})
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">
                                Active le {{ $activation->activated_at?->format('d/m/Y H:i') }}
                                @if($activation->last_check_at)
                                    - Derniere verification: {{ $activation->last_check_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full {{ $activation->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $activation->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="px-6 py-4 text-gray-500">Aucune activation</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
