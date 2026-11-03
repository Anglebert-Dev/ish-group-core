@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="$payment->reference">
    <x-common.page-header :title="Money::format($payment->gross_amount)" :description="$payment->reference">
        <x-slot:actions>
            <x-common.button href="{{ route('injongepay.payments.index') }}" variant="secondary" size="sm">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 items-start">
        <x-common.info-card :rows="[
            [__('body.status'), $payment->statusLabel()],
            [__('injongepay::injongepay.payer'), trim(($payment->buyer_name ?? '').' '.($payment->buyer_phone ?? '')) ?: '—'],
            [__('injongepay::injongepay.channel'), $payment->channel ?? '—'],
            [__('injongepay::injongepay.wallet'), $payment->wallet?->label()],
            [__('injongepay::injongepay.gtbank_reference'), $payment->bank_reference],
            [__('injongepay::injongepay.value_date'), $payment->value_date?->format('d M Y') ?? '—'],
            [__('injongepay::injongepay.received'), GeneralLibrary::formatDateTime($payment->created_at)],
            [__('injongepay::injongepay.confirmed_by_bank'), $payment->cleared_at ? GeneralLibrary::formatDateTime($payment->cleared_at) : __('injongepay::injongepay.not_yet')],
        ]" />
        <x-common.info-card :rows="[
            [__('injongepay::injongepay.gross'), Money::format($payment->gross_amount)],
            [__('injongepay::injongepay.fee').' ('.\Modules\InjongePay\Support\Rate::display($payment->fee_rate).'%)', Money::format($payment->fee_amount)],
            [__('injongepay::injongepay.net'), Money::format($payment->net_amount)],
        ]" />
        <div class="xl:col-span-2 bg-card border border-line rounded-xl overflow-hidden">
            <h2 class="font-display font-bold text-ink px-5 pt-4">{{ __('injongepay::injongepay.ledger') }}</h2>
            <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                @foreach ($entries as $entry)
                    <tr>
                        <td class="px-5 py-2 text-muted">{{ GeneralLibrary::formatDateTime($entry->created_at) }}</td>
                        <td class="px-5 py-2 font-mono">{{ $entry->reference }}</td>
                        <td class="px-5 py-2">{{ __('injongepay::injongepay.source_'.$entry->source) }} · {{ __('injongepay::injongepay.'.$entry->bucket) }}</td>
                        <td class="px-5 py-2 text-right {{ $entry->direction === 'credit' ? 'text-success' : 'text-danger' }}">{{ $entry->direction === 'credit' ? '+' : '−' }}{{ Money::format($entry->amount) }}</td>
                    </tr>
                @endforeach
            </tbody></table>
        </div>
    </div>
</x-layouts.app>
