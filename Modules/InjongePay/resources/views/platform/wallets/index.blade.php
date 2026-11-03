@php use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepay::injongepay.wallets')">
    <x-common.page-header :title="__('injongepay::injongepay.wallets')" :description="__('injongepay::injongepay.wallets_description')">
        <x-slot:actions>
            @if ($waiting > 0 && \App\Helpers\AuthHelper::can('update injongepay_platform.bank'))
                <x-common.action-button :action="route('injongepay.platform.wallets.sync-waiting')">{{ __('injongepay::injongepay.sync_waiting', ['count' => $waiting]) }}</x-common.action-button>
            @endif
            @if (\App\Helpers\AuthHelper::can('create injongepay_platform.wallet'))
                <x-common.button href="{{ route('injongepay.platform.wallets.create') }}" size="sm">{{ __('injongepay::injongepay.wallet_add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('injongepay.platform.wallets.index')" :open="request()->hasAny(['business', 'status', 'search'])">
        <x-common.select name="business" :label="__('body.business')" :options="['' => '—'] + $businesses" :value="request('business')" hide-placeholder searchable compact />
        <x-common.select name="status" :label="__('injongepay::injongepay.bank_status')" :options="['' => '—'] + collect(['NOT_REGISTERED', 'REGISTERING', 'PENDING', 'ACTIVE', 'INACTIVE', 'CLOSED', 'FAILED'])->mapWithKeys(fn ($s) => [$s => __('injongepay::injongepay.bank_status_'.$s)])->all()" :value="request('status')" hide-placeholder compact />
        <x-common.input name="search" :label="__('body.search')" :value="request('search')" :placeholder="__('injongepay::injongepay.search_wallet')" compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('injongepay::injongepay.merchant_code'), __('body.name'), __('body.business'), __('injongepay::injongepay.bank_status'), __('injongepay::injongepay.available'), __('injongepay::injongepay.pending'), '']">
        @forelse ($wallets as $wallet)
            <tr>
                <td class="px-5 py-3 font-mono">{{ $wallet->merchant_code }}</td>
                <td class="px-5 py-3 font-semibold text-ink">{{ $wallet->name }}</td>
                <td class="px-5 py-3">{{ $wallet->business?->name }} @if ($wallet->isInternal())<x-common.badge color="muted">{{ __('injongepay::injongepay.internal') }}</x-common.badge>@endif</td>
                <td class="px-5 py-3">@include('injongepay::partials.bank-status', ['wallet' => $wallet])</td>
                <td class="px-5 py-3">{{ Money::format($wallet->available_balance) }}</td>
                <td class="px-5 py-3 text-muted">{{ Money::format($wallet->pending_balance) }}</td>
                <td class="px-5 py-3 text-right"><a href="{{ route('injongepay.platform.wallets.show', $wallet->uuid) }}" class="text-brand font-semibold">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_wallets_platform') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$wallets" class="mt-4" />
</x-layouts.app>
