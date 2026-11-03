@php use Modules\InjongePay\Support\Money; @endphp
<x-layouts.app :title="__('injongepay::injongepay.overview')">
    <x-common.page-header :title="__('injongepay::injongepay.overview')" :description="__('injongepay::injongepay.overview_description')" />

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        @foreach ([
            [__('injongepay::injongepay.available'), Money::format($available)],
            [__('injongepay::injongepay.pending'), Money::format($pending)],
            [__('injongepay::injongepay.total_balance'), Money::format($available + $pending)],
            [__('injongepay::injongepay.today_payments'), ($today->count ?? 0).' · '.Money::format((int) ($today->gross ?? 0))],
        ] as [$label, $value])
            <div class="bg-card border border-line rounded-xl p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                <p class="text-xl font-display font-bold text-ink mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <h2 class="font-display font-bold text-ink mb-3">{{ __('injongepay::injongepay.wallets') }}</h2>
    @if ($wallets->isEmpty())
        <x-common.empty-state :title="__('injongepay::injongepay.no_wallets')" />
    @else
        <x-common.table :headers="[__('body.name'), __('injongepay::injongepay.merchant_code'), __('injongepay::injongepay.bank_status'), __('injongepay::injongepay.available'), __('injongepay::injongepay.pending')]">
            @foreach ($wallets as $wallet)
                <tr>
                    <td class="px-5 py-3 font-semibold text-ink">{{ $wallet->name }}</td>
                    <td class="px-5 py-3 font-mono">{{ $wallet->merchant_code }}</td>
                    <td class="px-5 py-3">@include('injongepay::partials.bank-status', ['wallet' => $wallet])</td>
                    <td class="px-5 py-3">{{ Money::format($wallet->available_balance) }}</td>
                    <td class="px-5 py-3 text-muted">{{ Money::format($wallet->pending_balance) }}</td>
                </tr>
            @endforeach
        </x-common.table>
    @endif
</x-layouts.app>
