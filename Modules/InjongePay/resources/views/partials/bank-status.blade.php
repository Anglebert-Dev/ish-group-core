@php
    $color = match ($wallet->bank_status) {
        'ACTIVE' => 'success',
        'PENDING', 'REGISTERING' => 'warning',
        'FAILED', 'CLOSED', 'INACTIVE' => 'danger',
        default => 'muted',
    };
@endphp
<x-common.badge :color="$color">{{ __('injongepay::injongepay.bank_status_'.$wallet->bank_status) }}</x-common.badge>
@unless ($wallet->is_enabled)
    <x-common.badge color="danger">{{ __('injongepay::injongepay.disabled') }}</x-common.badge>
@endunless
