@php use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$connection->integrator?->name">
    <x-common.page-header :title="$connection->integrator?->name" :description="$connection->client_id">
        <x-slot:actions>
            @if (AuthHelper::can('update injongepay_connect.connection'))
                <x-common.action-button :action="route('injongepayconnect.connections.rotate', $connection)" color="warning">{{ __('injongepayconnect::connect.rotate') }}</x-common.action-button>
                <x-common.action-button :action="route('injongepayconnect.connections.toggle', $connection)" :color="$connection->is_enabled ? 'danger' : 'success'">{{ $connection->is_enabled ? __('injongepayconnect::connect.switch_off') : __('injongepayconnect::connect.switch_on') }}</x-common.action-button>
            @endif
            <x-common.button href="{{ route('injongepayconnect.connections.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    @if ($secret)
        <div class="rounded-xl border border-warning/40 bg-warning/10 p-4 mb-6 space-y-1">
            <p class="font-semibold text-ink">{{ __('injongepayconnect::connect.secret_once') }}</p>
            <p class="font-mono text-sm">client_id: <span class="select-all">{{ $secret['client_id'] }}</span></p>
            <p class="font-mono text-sm break-all">client_secret: <span class="select-all">{{ $secret['secret'] }}</span></p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="space-y-6">
            <x-common.info-card :rows="[
                [__('body.status'), $connection->is_enabled ? __('injongepayconnect::connect.on') : __('injongepayconnect::connect.off')],
                [__('injongepayconnect::connect.system'), $connection->integrator?->name],
                ['Client ID', $connection->client_id],
                ['API', url('/api/partner/v1')],
                [__('injongepayconnect::connect.last_used'), $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—'],
                [__('injongepayconnect::connect.created'), GeneralLibrary::formatDateTime($connection->created_at)],
            ]" />
            @if (AuthHelper::can('update injongepay_connect.connection'))
                <form action="{{ route('injongepayconnect.connections.addresses', $connection) }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <x-common.input name="allowed_ips" :label="__('injongepayconnect::connect.allowed_ips')" :value="$connection->allowed_ips" :hint="__('injongepayconnect::connect.allowed_ips_hint')" />
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>
            @endif
        </div>
        <div class="xl:col-span-2">
            <h2 class="font-display font-bold text-ink mb-3">{{ __('injongepayconnect::connect.calls') }}</h2>
            <x-common.table :headers="[__('injongepayconnect::connect.when'), __('injongepayconnect::connect.request'), 'HTTP']">
                @forelse ($calls as $call)
                    <tr>
                        <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($call->created_at, 'd M Y H:i:s') }}</td>
                        <td class="px-5 py-2 font-mono text-xs">{{ $call->method }} /{{ $call->path }}</td>
                        <td class="px-5 py-2"><x-common.badge :color="$call->status < 400 ? 'success' : ($call->status < 500 ? 'warning' : 'danger')">{{ $call->status }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-muted">{{ __('injongepayconnect::connect.no_calls') }}</td></tr>
                @endforelse
            </x-common.table>
            <x-common.pagination :paginator="$calls" class="mt-4" />
        </div>
    </div>
</x-layouts.app>
