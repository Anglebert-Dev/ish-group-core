@props(['dailyFee' => null, 'hidden' => false])

@php
    $amount = $dailyFee ?? ($chauffeurDailyFee ?? 0);
@endphp

@if ((float) $amount > 0)
    <p {{ $attributes->merge(['class' => 'text-sm font-semibold text-brand', 'data-chauffeur-fee' => '1']) }} @if ($hidden) hidden @endif>
        {{ __('body.chauffeur_fee_amount', ['amount' => number_format($amount, 0)]) }}
    </p>
@endif
