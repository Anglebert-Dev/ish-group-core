@php use App\Helpers\AuthHelper; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepayconnect::connect.platform_title')">
    <x-common.page-header :title="__('injongepayconnect::connect.platform_title')" :description="__('injongepayconnect::connect.platform_description')" />

    @if ($secret)
        <div class="rounded-xl border border-warning/40 bg-warning/10 p-4 mb-6 space-y-1">
            <p class="font-semibold text-ink">{{ __('injongepayconnect::connect.secret_once') }}</p>
            <p class="font-mono text-sm">client_id: <span class="select-all">{{ $secret['client_id'] }}</span></p>
            <p class="font-mono text-sm break-all">client_secret: <span class="select-all">{{ $secret['secret'] }}</span></p>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-4">
            <h2 class="font-display font-bold text-ink">{{ __('injongepayconnect::connect.connections') }}</h2>
            @forelse ($connections as $connection)
                <div class="bg-card border border-line rounded-xl p-5 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-ink">{{ $connection->business?->name }}</span>
                        <span class="text-muted">· {{ $connection->integrator?->name }} ({{ $connection->integrator?->profile }})</span>
                        <x-common.badge :color="$connection->is_enabled ? 'success' : 'muted'">{{ $connection->is_enabled ? __('injongepayconnect::connect.on') : __('injongepayconnect::connect.off') }}</x-common.badge>
                    </div>
                    <p class="text-sm font-mono">{{ $connection->client_id }} · {{ __('injongepayconnect::connect.last_used') }}: {{ $connection->last_used_at ? GeneralLibrary::formatDateTime($connection->last_used_at) : '—' }}</p>
                    @if (AuthHelper::can('update injongepay_connect_platform.connection'))
                        <div class="flex flex-wrap gap-2">
                            <x-common.action-button :action="route('injongepayconnect.platform.connections.rotate', $connection->uuid)" color="warning">{{ __('injongepayconnect::connect.rotate') }}</x-common.action-button>
                            <x-common.action-button :action="route('injongepayconnect.platform.connections.toggle', $connection->uuid)" :color="$connection->is_enabled ? 'danger' : 'success'">{{ $connection->is_enabled ? __('injongepayconnect::connect.switch_off') : __('injongepayconnect::connect.switch_on') }}</x-common.action-button>
                        </div>
                        <form action="{{ route('injongepayconnect.platform.connections.limits', $connection->uuid) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                            @csrf @method('PUT')
                            <x-common.input name="rate_limit" :id="'rl-'.$connection->id" type="number" :label="__('injongepayconnect::connect.rate_limit')" :value="$connection->rate_limit" :hint="__('injongepayconnect::connect.rate_limit_hint', ['n' => config('injongepayconnect.rate_limit')])" compact />
                            <x-common.input name="allowed_ips" :id="'ip-'.$connection->id" :label="__('injongepayconnect::connect.allowed_ips')" :value="$connection->allowed_ips" compact />
                            <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.save') }}</x-common.button>
                        </form>
                    @endif
                </div>
            @empty
                <x-common.empty-state :title="__('injongepayconnect::connect.no_connections')" />
            @endforelse

            <div class="bg-card border border-line rounded-xl overflow-hidden">
                <h2 class="font-display font-bold text-ink px-5 pt-4">{{ __('injongepayconnect::connect.calls') }}</h2>
                <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                    @forelse ($calls as $call)
                        <tr>
                            <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($call->created_at, 'd M H:i:s') }}</td>
                            <td class="px-5 py-2 font-mono">{{ $call->method }} /{{ $call->path }}</td>
                            <td class="px-5 py-2">{{ $call->caller }}</td>
                            <td class="px-5 py-2"><x-common.badge :color="$call->status < 400 ? 'success' : ($call->status < 500 ? 'warning' : 'danger')">{{ $call->status }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-muted">{{ __('injongepayconnect::connect.no_calls') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>

        <div class="space-y-6">
            @if (AuthHelper::can('create injongepay_connect_platform.connection') && $integrators->isNotEmpty())
                <form action="{{ route('injongepayconnect.platform.connections.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('injongepayconnect::connect.connect_business') }}</h2>
                    <x-common.select name="integrator" :label="__('injongepayconnect::connect.integrator')" :options="$integrators->where('is_enabled', true)->pluck('name', 'uuid')->all()" required />
                    <x-common.select name="business" :label="__('body.business')" :options="$businesses" required searchable />
                    <x-common.button type="submit" size="sm">{{ __('injongepayconnect::connect.create_credentials') }}</x-common.button>
                </form>
            @endif
            <div class="bg-card border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('injongepayconnect::connect.integrators') }}</h2>
                @foreach ($integrators as $integrator)
                    <p class="text-sm"><span class="font-semibold text-ink">{{ $integrator->name }}</span> · <span class="font-mono">{{ $integrator->profile }}</span></p>
                @endforeach
                @if (AuthHelper::can('create injongepay_connect_platform.integrator'))
                    <form action="{{ route('injongepayconnect.platform.integrators.store') }}" method="POST" class="space-y-3 pt-3 border-t border-line">
                        @csrf
                        <x-common.input name="name" :label="__('body.name')" required compact />
                        <x-common.select name="profile" :label="__('injongepayconnect::connect.profile')" :options="$profiles" required hide-placeholder compact />
                        <x-common.input name="contact_email" type="email" :label="__('injongepayconnect::connect.contact_email')" compact />
                        <x-common.button type="submit" size="sm" variant="secondary">{{ __('injongepayconnect::connect.add_integrator') }}</x-common.button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
