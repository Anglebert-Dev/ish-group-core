@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<x-layouts.app :title="__('injongepay::injongepay.payments')">
    <x-common.page-header :title="__('injongepay::injongepay.payments')" :description="__('injongepay::injongepay.payments_description')" />
    @include('injongepay::partials.test-mode')

    <x-common.filter-panel :action="route('injongepay.payments.index')" :open="request()->hasAny(['wallet', 'from', 'to', 'search'])">
        <x-common.select name="wallet" :label="__('injongepay::injongepay.wallet')" :options="['' => '—'] + $wallets" :value="request('wallet')" hide-placeholder compact />
        <x-common.input name="from" type="date" :label="__('injongepay::injongepay.from')" :value="request('from')" compact />
        <x-common.input name="to" type="date" :label="__('injongepay::injongepay.to')" :value="request('to')" compact />
        <x-common.input name="search" :label="__('body.search')" :value="request('search')" :placeholder="__('injongepay::injongepay.search_payment')" compact />
    </x-common.filter-panel>

    <x-common.table :headers="[__('injongepay::injongepay.when'), __('injongepay::injongepay.payer'), __('injongepay::injongepay.wallet'), __('injongepay::injongepay.gross'), __('injongepay::injongepay.fee'), __('injongepay::injongepay.net'), __('body.status'), '']">
        @forelse ($payments as $payment)
            <tr>
                <td class="px-5 py-3 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($payment->created_at) }}</td>
                <td class="px-5 py-3"><span class="font-semibold text-ink">{{ $payment->buyer_name ?? '—' }}</span><br><span class="text-xs text-muted">{{ $payment->buyer_phone }} · {{ $payment->channel }}</span></td>
                <td class="px-5 py-3">{{ $payment->wallet?->name }}</td>
                <td class="px-5 py-3">{{ Money::format($payment->gross_amount) }}</td>
                <td class="px-5 py-3 text-muted">{{ Money::format($payment->fee_amount) }}</td>
                <td class="px-5 py-3 font-semibold">{{ Money::format($payment->net_amount) }}</td>
                <td class="px-5 py-3">@include('injongepay::partials.payment-status', ['payment' => $payment])</td>
                <td class="px-5 py-3 text-right"><a href="{{ route('injongepay.payments.show', $payment) }}" class="text-brand font-semibold">{{ __('body.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('injongepay::injongepay.no_payments') }}</td></tr>
        @endforelse
    </x-common.table>
    <x-common.pagination :paginator="$payments" class="mt-4" />
</x-layouts.app>
