<x-layouts.app :title="$call->method.' /'.$call->path">
    <x-common.page-header :title="$call->method.' /'.$call->path" :description="\App\Libraries\GeneralLibrary::formatDateTime($call->created_at, 'd M Y H:i:s')">
        <x-slot:actions>
            <x-common.button href="{{ $connection ? route('injongepayconnect.platform.connections.show', $connection->uuid) : route('injongepayconnect.platform.calls.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <x-common.info-card :rows="[
            ['HTTP', $call->status],
            [__('body.business'), $connection?->business?->name ?? $call->caller ?? '—'],
            [__('injongepayconnect::connect.integrator'), $connection?->integrator?->name ?? '—'],
            ['IP', $call->ip ?? '—'],
            [__('injongepayconnect::connect.duration'), $call->duration_ms.' ms'],
        ]" />
        <div class="xl:col-span-2 space-y-4">
            @foreach (['request' => __('injongepayconnect::connect.request'), 'response' => __('injongepayconnect::connect.response')] as $field => $label)
                <div class="bg-card border border-line rounded-xl p-5">
                    <h2 class="font-display font-bold text-ink mb-2">{{ $label }}</h2>
                    <pre class="text-xs bg-surface border border-line rounded-lg p-3 overflow-x-auto">{{ json_encode($call->{$field}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
