<x-layouts.app :title="__('billing::billing.choose_plan')">
    <x-common.page-header :title="__('billing::billing.choose_plan')" :description="__('billing::billing.choose_plan_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('billing.index') }}" variant="secondary" size="sm">← {{ __('billing::billing.my_subscription') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    @if ($packages->isEmpty())
        <x-common.empty-state :title="__('billing::billing.no_packages')" />
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach ($packages as $package)
                @php $isCurrent = $subscription && $subscription->package_id === $package->id && $subscription->status->grantsAccess(); @endphp
                <x-billing::package-card :package="$package" :current="$isCurrent"
                    :action="$isCurrent ? null : route('billing.checkout', $package)"
                    :action-label="__('billing::billing.select')" />
            @endforeach
        </div>
    @endif
</x-layouts.app>
