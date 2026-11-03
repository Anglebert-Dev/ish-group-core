@php use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepay::injongepay.finance')">
    <x-common.page-header :title="__('injongepay::injongepay.finance')" :description="__('injongepay::injongepay.finance_description')">
        <x-slot:actions>
            <x-common.action-button :href="route('injongepay.platform.finance.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()])">Excel</x-common.action-button>
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')
    @include('injongepay::partials.period')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl border p-4 {{ $difference === 0 ? 'border-success/40 bg-success/5' : 'border-danger/40 bg-danger/5' }}">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.books') }}</p>
            <p class="font-semibold mt-1 {{ $difference === 0 ? 'text-success' : 'text-danger' }}">{{ $difference === 0 ? __('injongepay::injongepay.platform_balanced') : __('injongepay::injongepay.platform_unbalanced', ['amount' => Money::format($difference)]) }}</p>
        </div>
        <div class="bg-card border border-line rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.internal_wallet_platform_revenue') }}</p>
            <p class="text-lg font-display font-bold text-ink mt-1">{{ Money::format($revenue?->available_balance ?? 0) }}</p>
        </div>
        <div class="bg-card border border-line rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.internal_wallet_bank_cut') }}</p>
            <p class="text-lg font-display font-bold text-ink mt-1">{{ Money::format($bankCut?->available_balance ?? 0) }}</p>
        </div>
    </div>
    @include('injongepay::partials.tiles')

    <x-common.table :headers="[__('body.business'), __('injongepay::injongepay.payments'), __('injongepay::injongepay.gross'), __('injongepay::injongepay.fee'), __('injongepay::injongepay.tile_cashouts_paid'), __('injongepay::injongepay.available'), __('injongepay::injongepay.pending')]">
        @forelse ($rows as $row)
            <tr>
                <td class="px-5 py-3 font-semibold text-ink">{{ $row->name }}</td>
                <td class="px-5 py-3">{{ (int) $row->payments }}</td>
                <td class="px-5 py-3">{{ Money::format((int) $row->gross) }}</td>
                <td class="px-5 py-3">{{ Money::format((int) $row->fees) }}</td>
                <td class="px-5 py-3">{{ Money::format((int) $row->paid) }}</td>
                <td class="px-5 py-3">{{ Money::format((int) $row->available) }}</td>
                <td class="px-5 py-3 text-muted">{{ Money::format((int) $row->pending) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_payments') }}</td></tr>
        @endforelse
    </x-common.table>
</x-layouts.app>
