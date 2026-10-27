@props([
    'vehicleName' => null,
    'vehicleDaily' => 0,
    'rentalDays' => 0,
    'categorySubtotal' => 0,
    'chauffeurFee' => 0,
    'deliveryFee' => 0,
    'collectionFee' => 0,
    'extraLines' => null,
    'totalAmount' => 0,
    'live' => false,
])

@php
    $extraLines = $extraLines ?? collect();
@endphp

<div
    {{ $attributes->merge([
        'class' => 'rounded-xl border border-line bg-surface p-4 sm:p-5 space-y-3',
        'data-booking-payment-summary' => $live ? '1' : null,
    ]) }}
>
    <div class="flex items-end justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-ink">{{ __('body.payment_summary') }}</p>
            <p class="text-xs text-muted mt-0.5">{{ __('body.payment_summary_hint') }}</p>
        </div>
        @if ($live)
            <p class="text-xs text-muted tabular-nums" data-summary-days hidden></p>
        @endif
    </div>

    <div class="space-y-1.5" data-summary-lines>
        @unless ($live)
            <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">
                <span class="text-ink min-w-0">
                    {{ $vehicleName }}
                    <span class="text-muted">× {{ $rentalDays }} {{ $rentalDays === 1 ? __('body.day') : __('body.days') }}</span>
                </span>
                <span class="shrink-0 text-muted tabular-nums">{{ number_format((float) $categorySubtotal, 0) }} RWF</span>
            </div>
            @if ((float) $deliveryFee > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">
                    <span class="text-ink">{{ __('body.delivery_fee') }}</span>
                    <span class="shrink-0 text-muted tabular-nums">{{ number_format((float) $deliveryFee, 0) }} RWF</span>
                </div>
            @endif
            @if ((float) $collectionFee > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">
                    <span class="text-ink">{{ __('body.collection_fee') }}</span>
                    <span class="shrink-0 text-muted tabular-nums">{{ number_format((float) $collectionFee, 0) }} RWF</span>
                </div>
            @endif
            @if ((float) $chauffeurFee > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">
                    <span class="text-ink">{{ __('body.chauffeur_fee_line', ['days' => $rentalDays]) }}</span>
                    <span class="shrink-0 text-muted tabular-nums">{{ number_format((float) $chauffeurFee, 0) }} RWF</span>
                </div>
            @endif
            @foreach ($extraLines as $line)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg bg-white px-3 py-2">
                    <span class="text-ink">{{ $line['extra']->name }} × {{ $line['quantity'] }}</span>
                    <span class="shrink-0 text-muted tabular-nums">{{ number_format((float) $line['total'], 0) }} RWF</span>
                </div>
            @endforeach
        @endunless
    </div>

    <div class="pt-3 border-t border-line flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-ink">{{ __('body.total_to_pay') }}</p>
        <p class="font-display font-bold text-2xl text-brand tabular-nums">
            <span data-summary-total>{{ number_format((float) $totalAmount, 0) }}</span>
            <span class="text-sm font-semibold text-brand/80">RWF</span>
        </p>
    </div>
</div>
