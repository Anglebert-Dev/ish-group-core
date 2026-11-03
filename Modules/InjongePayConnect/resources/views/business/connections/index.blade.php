@php use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepayconnect::connect.business_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.business_title')" :description="__('injongepayconnect::connect.business_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create injongepay_connect.connection'))
                <x-common.button href="{{ route('injongepayconnect.connections.create') }}" size="sm">+ {{ __('injongepayconnect::connect.connect_system') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepayconnect::business._tabs')

    <x-common.table :headers="[__('injongepayconnect::connect.integrator'), 'Client ID', __('body.status'), __('injongepayconnect::connect.last_used'), '']">
        @forelse ($connections as $connection)
            <tr class="hover:bg-surface">
                <td class="px-5 py-3 font-semibold text-ink"><a class="hover:text-brand" href="{{ route('injongepayconnect.connections.show', $connection) }}">{{ $connection->integrator?->name }}</a></td>
                <td class="px-5 py-3 font-mono text-xs">{{ $connection->client_id }}</td>
                <td class="px-5 py-3">@include('injongepayconnect::platform._status', ['on' => $connection->is_enabled])</td>
                <td class="px-5 py-3 text-muted">{{ $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—' }}</td>
                <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepayconnect.connections.show', $connection) }}">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_connections_business') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$connections" class="mt-4" />
</x-layouts.app>
