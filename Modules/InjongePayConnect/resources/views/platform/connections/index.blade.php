@php use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepayconnect::connect.platform_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.platform_title')" :description="__('injongepayconnect::connect.platform_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create injongepay_connect_platform.connection'))
                <x-common.button href="{{ route('injongepayconnect.platform.connections.create') }}" size="sm">+ {{ __('injongepayconnect::connect.connect_business') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepayconnect::platform._tabs')

    <x-common.filter-panel :action="route('injongepayconnect.platform.connections.index')" :open="request()->hasAny(['q', 'integrator', 'status'])">
        <x-common.input name="q" :label="__('body.search')" :value="request('q')" :placeholder="__('injongepayconnect::connect.search_connections')" compact />
        <x-common.select name="integrator" :label="__('injongepayconnect::connect.integrator')" :options="['' => __('body.all')] + $integrators" :value="request('integrator')" hide-placeholder compact />
        <x-common.select name="status" :label="__('body.status')" :options="['' => __('body.all'), 'on' => __('injongepayconnect::connect.on'), 'off' => __('injongepayconnect::connect.off')]" :value="request('status')" hide-placeholder compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('body.business'), __('injongepayconnect::connect.integrator'), 'Client ID', __('body.status'), __('injongepayconnect::connect.last_used'), '']">
        @forelse ($connections as $connection)
            <tr class="hover:bg-surface">
                <td class="px-5 py-3 font-semibold text-ink"><a class="hover:text-brand" href="{{ route('injongepayconnect.platform.connections.show', $connection->uuid) }}">{{ $connection->business?->name }}</a></td>
                <td class="px-5 py-3">{{ $connection->integrator?->name }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ $connection->client_id }}</td>
                <td class="px-5 py-3">@include('injongepayconnect::platform._status', ['on' => $connection->is_enabled])</td>
                <td class="px-5 py-3 text-muted">{{ $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—' }}</td>
                <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepayconnect.platform.connections.show', $connection->uuid) }}">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_connections') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$connections" class="mt-4" />
</x-layouts.app>
