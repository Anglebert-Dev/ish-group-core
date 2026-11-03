<x-layouts.app :title="__('injongepayconnect::connect.add_integrator')">
    <x-common.page-header :title="__('injongepayconnect::connect.add_integrator')" :description="__('injongepayconnect::connect.add_integrator_description')" />
    <form action="{{ route('injongepayconnect.platform.integrators.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4 max-w-xl">
        @csrf
        @include('injongepayconnect::platform.integrators._form', ['integrator' => null])
        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
            <x-common.button href="{{ route('injongepayconnect.platform.integrators.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
