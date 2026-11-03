@if ($payment->payment_status === 'REVERSED')
    <x-common.badge color="danger">{{ __('injongepay::injongepay.payment_REVERSED') }}</x-common.badge>
@elseif ($payment->payment_status === 'CASHED_OUT')
    <x-common.badge color="muted">{{ __('injongepay::injongepay.payment_CASHED_OUT') }}</x-common.badge>
@elseif (! $payment->cleared_at)
    <x-common.badge color="warning">{{ __('injongepay::injongepay.payment_pending') }}</x-common.badge>
@else
    <x-common.badge color="success">{{ __('injongepay::injongepay.payment_AVAILABLE') }}</x-common.badge>
@endif
