<x-admin-layout title="Facture {{ $invoice->number }}">
    <div class="mb-6">
        <a href="{{ route('admin.invoices.index') }}" class="text-indigo-600 hover:underline">&larr; Retour aux factures</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Facture {{ $invoice->number }}</h1>
                <p class="text-gray-500">Emise le {{ $invoice->issued_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="px-3 py-1 rounded-full
                    {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $invoice->status === 'open' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $invoice->status === 'void' ? 'bg-gray-100 text-gray-800' : '' }}
                    {{ $invoice->status === 'uncollectible' ? 'bg-red-100 text-red-800' : '' }}
                ">
                    {{ $invoice->status }}
                </span>
                @if($invoice->local_pdf_path)
                    <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                        Telecharger PDF
                    </a>
                @elseif($invoice->stripe_pdf_url)
                    <a href="{{ $invoice->stripe_pdf_url }}" target="_blank" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        PDF Stripe
                    </a>
                @endif
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Client</p>
                @if($invoice->user)
                    <a href="{{ route('admin.users.show', $invoice->user) }}" class="text-indigo-600 hover:underline">
                        {{ $invoice->user->name ?? $invoice->user->email }}
                    </a>
                    <p class="text-sm text-gray-500">{{ $invoice->user->email }}</p>
                @else
                    <p class="text-gray-400">-</p>
                @endif
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Produit</p>
                @if($invoice->license?->product)
                    <a href="{{ route('admin.products.show', $invoice->license->product) }}" class="text-indigo-600 hover:underline">
                        {{ $invoice->license->product->name }}
                    </a>
                @else
                    <p class="text-gray-400">-</p>
                @endif
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Licence</p>
                @if($invoice->license)
                    <a href="{{ route('admin.licenses.show', $invoice->license) }}" class="text-indigo-600 hover:underline font-mono text-sm">
                        {{ Str::limit($invoice->license->uuid, 16) }}
                    </a>
                @else
                    <p class="text-gray-400">-</p>
                @endif
            </div>
            <div class="bg-gray-50 p-4 rounded">
                <p class="text-sm text-gray-500">Stripe Invoice</p>
                <code class="font-mono text-sm">{{ $invoice->stripe_invoice_id }}</code>
            </div>
        </div>

        <div class="mt-6 bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Montants</h3>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Montant HT</span>
                    <span class="font-medium">{{ number_format($invoice->amount_without_tax / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">TVA</span>
                    <span class="font-medium">{{ $invoice->formatted_tax }}</span>
                </div>
                <div class="border-t pt-2 flex justify-between">
                    <span class="text-gray-900 font-semibold">Montant TTC</span>
                    <span class="text-lg font-bold text-gray-900">{{ $invoice->formatted_amount }}</span>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
