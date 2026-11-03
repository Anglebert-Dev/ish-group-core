@php use Modules\InjongePay\Support\Money; use App\Libraries\GeneralLibrary; @endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    @if ($payments->isNotEmpty() || \App\Helpers\AuthHelper::can('list injongepay.payment'))
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="flex items-center px-5 pt-4">
                <h3 class="font-semibold text-ink flex-1">{{ __('injongepay::injongepay.dash_latest_payments') }}</h3>
                <a class="text-sm text-brand font-semibold" href="{{ route('injongepay.payments.index') }}">{{ __('injongepay::injongepay.see_all') }}</a>
            </div>
            <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($payment->created_at, 'd M H:i') }}</td>
                        <td class="px-5 py-2"><a class="hover:text-brand" href="{{ route('injongepay.payments.show', $payment) }}">{{ $payment->buyer_name ?? $payment->buyer_phone ?? '—' }}</a><br><span class="text-xs text-muted">{{ $payment->wallet?->name }}</span></td>
                        <td class="px-5 py-2 font-semibold whitespace-nowrap">{{ Money::format($payment->gross_amount) }}</td>
                        <td class="px-5 py-2 text-right">@include('injongepay::partials.payment-status', ['payment' => $payment])</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_payments') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
    @endif
    @if (\App\Helpers\AuthHelper::can('list injongepay.cashout'))
        <div class="bg-card border border-line rounded-xl overflow-hidden">
            <div class="flex items-center px-5 pt-4">
                <h3 class="font-semibold text-ink flex-1">{{ __('injongepay::injongepay.dash_latest_cashouts') }}</h3>
                <a class="text-sm text-brand font-semibold" href="{{ route('injongepay.cashouts.index') }}">{{ __('injongepay::injongepay.see_all') }}</a>
            </div>
            <table class="w-full text-sm mt-2"><tbody class="divide-y divide-line">
                @forelse ($cashouts as $cashout)
                    <tr>
                        <td class="px-5 py-2 text-muted whitespace-nowrap">{{ GeneralLibrary::formatDateTime($cashout->created_at, 'd M H:i') }}</td>
                        <td class="px-5 py-2"><a class="hover:text-brand" href="{{ route('injongepay.cashouts.show', $cashout) }}">{{ $cashout->account_holder }}</a></td>
                        <td class="px-5 py-2 font-semibold whitespace-nowrap">{{ Money::format($cashout->amount) }}</td>
                        <td class="px-5 py-2 text-right">@include('injongepay::partials.cashout-status', ['cashout' => $cashout])</td>
                    </tr>
                @empty
                    <tr><td class="px-5 py-6 text-muted">{{ __('injongepay::injongepay.no_cashouts') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
    @endif
</div>
