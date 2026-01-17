<x-admin-layout title="{{ $user->email }}">
    <div class="mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux clients</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $user->email }}</h1>
                <p class="text-gray-500">{{ $user->name ?? 'Sans nom' }}</p>
            </div>
            <a href="{{ route('admin.users.edit', $user) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                Modifier
            </a>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Telephone</p>
                <p class="font-medium">{{ $user->phone ?? '-' }}</p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Adresse</p>
                <p class="font-medium">
                    {{ $user->address_line1 ?? '-' }}<br>
                    @if($user->address_line2){{ $user->address_line2 }}<br>@endif
                    {{ $user->postal_code }} {{ $user->city }}<br>
                    {{ $user->country }}
                </p>
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">TVA</p>
                <p class="font-medium">{{ $user->vat_number ?? '-' }}</p>
            </div>
        </div>

        @if($user->stripe_customer_id)
            <div class="mt-4 bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Stripe Customer ID</p>
                <code class="font-mono">{{ $user->stripe_customer_id }}</code>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Licences</h2>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($user->licenses as $license)
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <a href="{{ route('admin.licenses.show', $license) }}" class="text-indigo-600 hover:underline font-mono text-sm">
                                {{ Str::limit($license->uuid, 16) }}
                            </a>
                            <p class="text-sm text-gray-500">{{ $license->product?->name }} - {{ $license->type }}</p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full
                            {{ $license->status === 'active' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $license->status === 'suspended' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $license->status === 'expired' ? 'bg-gray-100 text-gray-800' : '' }}
                            {{ $license->status === 'revoked' ? 'bg-red-100 text-red-800' : '' }}
                        ">
                            {{ $license->status }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="px-6 py-4 text-gray-500">Aucune licence</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">Dernieres factures</h2>
            <a href="{{ route('admin.invoices.index', ['user_id' => $user->id]) }}" class="text-sm text-indigo-600 hover:underline">Voir tout</a>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($user->invoices as $invoice)
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->number }}</a>
                            <p class="text-sm text-gray-500">{{ $invoice->issued_at?->format('d/m/Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-medium">{{ $invoice->formatted_amount }}</p>
                            @if($invoice->stripe_pdf_url)
                                <a href="{{ $invoice->stripe_pdf_url }}" target="_blank" class="text-sm text-indigo-600 hover:underline">Telecharger PDF</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-4 text-gray-500">Aucune facture</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
