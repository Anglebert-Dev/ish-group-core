<x-layouts.app :title="__('injongepayconnect::connect.connect_system')">
    <x-common.page-header :title="__('injongepayconnect::connect.connect_system')" :description="__('injongepayconnect::connect.connect_system_description')" />

    @if ($integrators === [])
        <x-common.empty-state :title="__('injongepayconnect::connect.no_integrators_business')" />
    @else
        <form action="{{ route('injongepayconnect.connections.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4 max-w-xl">
            @csrf
            <x-common.select name="integrator" :label="__('injongepayconnect::connect.system')" :options="$integrators" :value="old('integrator')" required />
            <p class="text-sm text-muted">{{ __('injongepayconnect::connect.secret_shown_once_hint') }}</p>
            <div class="flex gap-3">
                <x-common.button type="submit">{{ __('injongepayconnect::connect.create_credentials') }}</x-common.button>
                <x-common.button href="{{ route('injongepayconnect.connections.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
            </div>
        </form>
    @endif
</x-layouts.app>
