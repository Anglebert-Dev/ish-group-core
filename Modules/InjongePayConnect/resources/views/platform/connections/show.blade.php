@php use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$connection->business?->name">
    <x-common.page-header :title="$connection->business?->name" :description="$connection->integrator?->name.' · '.$connection->client_id">
        <x-slot:actions>
            @if (AuthHelper::can('update injongepay_connect_platform.connection'))
                <x-common.action-button :action="route('injongepayconnect.platform.connections.rotate', $connection->uuid)" color="warning">{{ __('injongepayconnect::connect.rotate') }}</x-common.action-button>
                <x-common.action-button :action="route('injongepayconnect.platform.connections.toggle', $connection->uuid)" :color="$connection->is_enabled ? 'danger' : 'success'">{{ $connection->is_enabled ? __('injongepayconnect::connect.switch_off') : __('injongepayconnect::connect.switch_on') }}</x-common.action-button>
            @endif
            <x-common.button href="{{ route('injongepayconnect.platform.connections.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
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
                [__('body.business'), $connection->business?->name],
                [__('injongepayconnect::connect.integrator'), $connection->integrator?->name],
                [__('injongepayconnect::connect.profile'), $connection->integrator?->profile],
                ['Client ID', $connection->client_id],
                [__('injongepayconnect::connect.last_used'), $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—'],
                [__('injongepayconnect::connect.created'), GeneralLibrary::formatDateTime($connection->created_at)],
            ]" />
            @if (AuthHelper::can('update injongepay_connect_platform.connection'))
                <form action="{{ route('injongepayconnect.platform.connections.limits', $connection->uuid) }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <h2 class="font-display font-bold text-ink">{{ __('injongepayconnect::connect.limits') }}</h2>
                    <x-common.input name="rate_limit" type="number" :label="__('injongepayconnect::connect.rate_limit')" :value="$connection->rate_limit" :hint="__('injongepayconnect::connect.rate_limit_hint', ['n' => config('injongepayconnect.rate_limit')])" />
                    <x-common.input name="allowed_ips" :label="__('injongepayconnect::connect.allowed_ips')" :value="$connection->allowed_ips" :hint="__('injongepayconnect::connect.allowed_ips_hint')" />
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>
            @endif
        </div>
        <div class="xl:col-span-2">
            <h2 class="font-display font-bold text-ink mb-3">{{ __('injongepayconnect::connect.calls') }}</h2>
            @include('injongepayconnect::platform.calls._table', ['calls' => $calls, 'names' => null])
        </div>
    </div>
</x-layouts.app>
