<x-layouts.app :title="__('injongepay::injongepay.wallet_add')">
    <x-common.page-header :title="__('injongepay::injongepay.wallet_add')" :description="__('injongepay::injongepay.wallet_add_description')" />
    @include('injongepay::partials.test-mode')

    <form action="{{ route('injongepay.platform.wallets.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 sm:p-6 space-y-4 max-w-2xl">
        @csrf
        <x-common.select name="business" :label="__('body.business')" :options="$businesses" :value="old('business', $business)" required searchable />
        @include('injongepay::platform.wallets._details', ['wallet' => null])
        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('injongepay::injongepay.wallet_create') }}</x-common.button>
            <x-common.button href="{{ route('injongepay.platform.wallets.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
