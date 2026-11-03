<x-layouts.app :title="__('injongepayconnect::connect.platform_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.platform_title')" :description="__('injongepayconnect::connect.integrators_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create injongepay_connect_platform.integrator'))
                <x-common.button href="{{ route('injongepayconnect.platform.integrators.create') }}" size="sm">+ {{ __('injongepayconnect::connect.add_integrator') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepayconnect::platform._tabs')

    <x-common.filter-panel :action="route('injongepayconnect.platform.integrators.index')" :open="request()->hasAny(['q', 'profile'])">
        <x-common.input name="q" :label="__('body.search')" :value="request('q')" compact />
        <x-common.select name="profile" :label="__('injongepayconnect::connect.profile')" :options="['' => __('body.all')] + $profiles" :value="request('profile')" hide-placeholder compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('body.name'), __('injongepayconnect::connect.profile'), __('injongepayconnect::connect.connections'), __('body.status'), '']">
        @forelse ($integrators as $integrator)
            <tr class="hover:bg-surface">
                <td class="px-5 py-3 font-semibold text-ink"><a class="hover:text-brand" href="{{ route('injongepayconnect.platform.integrators.show', $integrator) }}">{{ $integrator->name }}</a></td>
                <td class="px-5 py-3">{{ $profiles[$integrator->profile] ?? $integrator->profile }}</td>
                <td class="px-5 py-3">{{ $integrator->connections_count }}</td>
                <td class="px-5 py-3">@include('injongepayconnect::platform._status', ['on' => $integrator->is_enabled])</td>
                <td class="px-5 py-3 text-right"><a class="text-brand font-semibold" href="{{ route('injongepayconnect.platform.integrators.show', $integrator) }}">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_integrators') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$integrators" class="mt-4" />
</x-layouts.app>
