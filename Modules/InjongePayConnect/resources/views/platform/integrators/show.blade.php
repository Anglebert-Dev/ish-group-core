@php use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$integrator->name">
    <x-common.page-header :title="$integrator->name" :description="$profiles[$integrator->profile] ?? $integrator->profile">
        <x-slot:actions>
            @if (AuthHelper::can('update injongepay_connect_platform.integrator'))
                <x-common.action-button :action="route('injongepayconnect.platform.integrators.toggle', $integrator)" :color="$integrator->is_enabled ? 'danger' : 'success'">{{ $integrator->is_enabled ? __('injongepayconnect::connect.switch_off') : __('injongepayconnect::connect.switch_on') }}</x-common.action-button>
            @endif
            <x-common.button href="{{ route('injongepayconnect.platform.integrators.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        @if (AuthHelper::can('update injongepay_connect_platform.integrator'))
            <form action="{{ route('injongepayconnect.platform.integrators.update', $integrator) }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                @csrf @method('PUT')
                @include('injongepayconnect::platform.integrators._form', ['integrator' => $integrator])
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
        <div class="xl:col-span-2">
            <h2 class="font-display font-bold text-ink mb-1">{{ __('injongepayconnect::connect.businesses_using') }}</h2>
            <p class="text-sm text-muted mb-3">{{ __('injongepayconnect::connect.businesses_using_hint') }}</p>
            <x-common.table :headers="[__('body.business'), __('body.status'), __('injongepayconnect::connect.last_used')]">
                @forelse ($connections as $connection)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3 font-semibold text-ink">{{ $connection->business?->name }}</td>
                        <td class="px-5 py-3">@include('injongepayconnect::platform._status', ['on' => $connection->is_enabled])</td>
                        <td class="px-5 py-3 text-muted">{{ $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_connections') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$connections" class="mt-4" />
        </div>
    </div>
</x-layouts.app>
