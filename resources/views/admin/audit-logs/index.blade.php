<x-admin-layout title="Logs d'audit">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Logs d'audit</h1>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form action="{{ route('admin.audit-logs.index') }}" method="GET" class="flex flex-wrap gap-4">
            <select name="action" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Toutes les actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                @endforeach
            </select>
            <select name="model_type" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les types</option>
                <option value="Product" {{ request('model_type') === 'Product' ? 'selected' : '' }}>Product</option>
                <option value="Price" {{ request('model_type') === 'Price' ? 'selected' : '' }}>Price</option>
                <option value="Release" {{ request('model_type') === 'Release' ? 'selected' : '' }}>Release</option>
                <option value="License" {{ request('model_type') === 'License' ? 'selected' : '' }}>License</option>
                <option value="User" {{ request('model_type') === 'User' ? 'selected' : '' }}>User</option>
                <option value="ApiToken" {{ request('model_type') === 'ApiToken' ? 'selected' : '' }}>ApiToken</option>
                <option value="AdminUser" {{ request('model_type') === 'AdminUser' ? 'selected' : '' }}>Admin</option>
            </select>
            <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">Filtrer</button>
            @if(request()->hasAny(['action', 'model_type']))
                <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-800">Reinitialiser</a>
            @endif
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cible</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($logs as $log)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $log->created_at->format('d/m/Y H:i:s') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            {{ $log->admin?->email ?? 'Systeme' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs rounded-full
                                {{ $log->action === 'created' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $log->action === 'updated' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $log->action === 'deleted' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $log->action === 'login' ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $log->action === 'logout' ? 'bg-gray-100 text-gray-800' : '' }}
                            ">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            {{ $log->model_label }}
                        </td>
                        <td class="px-6 py-4 text-sm max-w-xs truncate">
                            @if($log->action === 'login' && isset($log->new_values['method']))
                                via {{ $log->new_values['method'] }}
                            @elseif($log->action === 'updated' && $log->old_values)
                                @foreach(array_keys($log->old_values) as $key)
                                    <span class="text-gray-500">{{ $key }}</span>@if(!$loop->last), @endif
                                @endforeach
                            @elseif($log->action === 'created' && $log->new_values)
                                @php $preview = collect($log->new_values)->only(['name', 'email', 'slug', 'version'])->first() @endphp
                                @if($preview)
                                    {{ Str::limit($preview, 30) }}
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $log->ip_address }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">Aucun log</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</x-admin-layout>
