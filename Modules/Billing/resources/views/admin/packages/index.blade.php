<x-layouts.app :title="__('billing::billing.packages')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.packages')" :description="__('billing::billing.packages_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.billing.packages.create') }}" size="sm">+ {{ __('billing::billing.add_package') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    @if ($packages->isEmpty())
        <x-common.empty-state :title="__('billing::billing.no_packages')" :description="__('billing::billing.no_packages_hint')" />
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach ($packages as $package)
                <x-billing::package-card :package="$package" admin>
                    <x-slot:footer>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm text-muted">{{ trans_choice('billing::billing.n_subscribers', $subscriberCounts[$package->id] ?? 0, ['count' => $subscriberCounts[$package->id] ?? 0]) }}</span>
                            <div class="flex gap-1.5">
                                <x-common.action-button :href="route('superadmin.billing.packages.edit', $package)">{{ __('body.edit') }}</x-common.action-button>
                                <x-common.action-button :action="route('superadmin.billing.packages.destroy', $package)" method="DELETE" color="danger"
                                    data-confirm data-confirm-title="{{ __('body.delete') }} {{ $package->name }}?" data-confirm-variant="danger">
                                    {{ __('body.delete') }}
                                </x-common.action-button>
                            </div>
                        </div>
                    </x-slot:footer>
                </x-billing::package-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
