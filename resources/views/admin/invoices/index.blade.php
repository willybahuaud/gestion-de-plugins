<x-admin-layout title="Factures">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Factures</h1>
        <a href="{{ route('admin.invoices.export', request()->query()) }}" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
            Exporter CSV
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form action="{{ route('admin.invoices.index') }}" method="GET" class="flex flex-wrap gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher (numero, email, Stripe ID)"
                class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <select name="user_id" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les clients</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->email }}</option>
                @endforeach
            </select>
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les statuts</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Payee</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>En attente</option>
                <option value="void" {{ request('status') === 'void' ? 'selected' : '' }}>Annulee</option>
                <option value="uncollectible" {{ request('status') === 'uncollectible' ? 'selected' : '' }}>Irrecouvrable</option>
            </select>
            <input type="date" name="from" value="{{ request('from') }}" placeholder="Du"
                class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <input type="date" name="to" value="{{ request('to') }}" placeholder="Au"
                class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">Filtrer</button>
            @if(request()->hasAny(['search', 'user_id', 'status', 'from', 'to']))
                <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800">Reinitialiser</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Numero</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Montant TTC</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($invoices as $invoice)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-indigo-600 hover:underline font-mono text-sm">
                                {{ $invoice->number }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $invoice->issued_at->format('d/m/Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($invoice->user)
                                <a href="{{ route('admin.users.show', $invoice->user) }}" class="text-indigo-600 hover:underline">
                                    {{ $invoice->user->email }}
                                </a>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            {{ $invoice->license?->product?->name ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                            {{ $invoice->formatted_amount }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $invoice->status === 'paid' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $invoice->status === 'open' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $invoice->status === 'void' ? 'bg-gray-100 text-gray-800' : '' }}
                                {{ $invoice->status === 'uncollectible' ? 'bg-red-100 text-red-800' : '' }}
                            ">
                                {{ $invoice->status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @if($invoice->local_pdf_path)
                                <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="text-indigo-600 hover:underline" title="PDF sauvegarde">
                                    PDF
                                </a>
                            @elseif($invoice->stripe_pdf_url)
                                <a href="{{ $invoice->stripe_pdf_url }}" target="_blank" class="text-gray-500 hover:underline" title="PDF Stripe">
                                    PDF
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">Aucune facture</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $invoices->withQueryString()->links() }}
    </div>
</x-admin-layout>
