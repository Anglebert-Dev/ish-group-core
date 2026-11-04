@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; use App\Helpers\AuthHelper; @endphp
<x-layouts.app :title="$wallet->label()">
    <x-common.page-header :title="$wallet->name" :description="$wallet->merchant_code">
        <x-slot:actions>
            @if (\Modules\InjongePay\Gtbank\GtbankClient::isConfigured() && ! in_array($wallet->bank_status, ['NOT_REGISTERED', 'CLOSED'], true))
                <x-common.action-button :action="route('injongepay.wallets.check', $wallet->uuid)">{{ __('injongepay::injongepay.check_now') }}</x-common.action-button>
            @endif
            @if (AuthHelper::can('create injongepay.cashout') && $wallet->is_enabled && $wallet->available_balance >= 100)
                <x-common.button href="{{ route('injongepay.cashouts.index', ['wallet' => $wallet->uuid]) }}" size="sm">{{ __('injongepay::injongepay.cashout_send') }}</x-common.button>
            @endif
            @if (AuthHelper::can('create injongepay.transfer') && $wallet->is_enabled)
                <x-common.button href="{{ route('injongepay.transfers.index', ['wallet' => $wallet->uuid]) }}" size="sm" variant="secondary">{{ __('injongepay::injongepay.transfer_new') }}</x-common.button>
            @endif
            @if (AuthHelper::can('list injongepay.ledger'))
                <x-common.button href="{{ route('injongepay.ledger', $wallet->uuid) }}" size="sm" variant="secondary">{{ __('injongepay::injongepay.ledger') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>
    @include('injongepay::partials.test-mode')
    @if ($wallet->isFrozen())
        <div class="rounded-xl border border-danger/40 bg-danger/10 p-3 mb-4 text-sm text-ink">{{ __('injongepay::injongepay.wallet_frozen_business') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @foreach ([
            [__('injongepay::injongepay.available'), Money::format($wallet->available_balance)],
            [__('injongepay::injongepay.pending'), Money::format($wallet->pending_balance)],
            [__('injongepay::injongepay.today_payments'), $today->count.' · '.Money::format((int) $today->gross)],
            [__('injongepay::injongepay.month_payments'), $month->count.' · '.Money::format((int) $month->gross)],
            [__('injongepay::injongepay.cashouts_on_the_way'), Money::format($outgoing)],
        ] as [$label, $value])
            <div class="bg-card border border-line rounded-xl p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                <p class="text-lg font-display font-bold text-ink mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>
    <div class="mb-6">@include('injongepay::partials.bank-status', ['wallet' => $wallet])</div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="flex items-center px-5 pt-4">
                <h2 class="font-display font-bold text-ink flex-1">{{ __('injongepay::injongepay.payments') }}</h2>
                <a class="text-sm text-brand font-semibold" href="{{ route('injongepay.payments.index', ['wallet' => $wallet->uuid]) }}">{{ __('injongepay::injongepay.see_all') }}</a>
            </div>
            <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($payment->created_at) }}</td>
                        <td class="px-5 py-2"><a class="hover:text-brand" href="{{ route('injongepay.payments.show', $payment) }}">{{ $payment->buyer_name ?? $payment->buyer_phone ?? '—' }}</a></td>
                        <td class="px-5 py-2 font-semibold">{{ Money::format($payment->gross_amount) }}</td>
                        <td class="px-5 py-2 text-right">@include('injongepay::partials.payment-status', ['payment' => $payment])</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_payments') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="flex items-center px-5 pt-4">
                <h2 class="font-display font-bold text-ink flex-1">{{ __('injongepay::injongepay.cashouts') }}</h2>
                <a class="text-sm text-brand font-semibold" href="{{ route('injongepay.cashouts.index') }}">{{ __('injongepay::injongepay.see_all') }}</a>
            </div>
            <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                @forelse ($cashouts as $cashout)
                    <tr>
                        <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($cashout->created_at) }}</td>
                        <td class="px-5 py-2"><a class="hover:text-brand" href="{{ route('injongepay.cashouts.show', $cashout) }}">{{ $cashout->account_holder }}</a></td>
                        <td class="px-5 py-2 font-semibold">{{ Money::format($cashout->amount) }}</td>
                        <td class="px-5 py-2 text-right">@include('injongepay::partials.cashout-status', ['cashout' => $cashout])</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_cashouts') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
</x-layouts.app>
