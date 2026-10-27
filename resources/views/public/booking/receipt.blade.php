@php
    $rentalSubtotal = bcmul((string) ($booking->category_daily_price ?? 0), (string) max(1, (int) $booking->rental_days), 2);
    $vehicleLabel = $booking->assignedVehicle?->name() ?? $booking->category?->name ?? __('body.booking');
@endphp

<x-layouts.public :title="__('body.booking_invoice').' — '.$booking->booking_number">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
        <x-common.page-header :title="__('body.booking_invoice')" :description="$booking->booking_number">
            <x-slot:actions>
                <x-common.button :href="route('booking.receipt.pdf', $booking)" variant="secondary" size="sm">
                    {{ __('body.download_pdf') }}
                </x-common.button>
                <x-common.button :href="route('account.bookings.show', $booking)" variant="ghost" size="sm">
                    {{ __('body.go_to_booking_details') }}
                </x-common.button>
            </x-slot:actions>
        </x-common.page-header>

        <div class="bg-white border border-line rounded-2xl shadow-card overflow-hidden">
            <div class="px-5 sm:px-8 py-6 border-b border-line bg-surface/70 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $company }}</p>
                    <h2 class="font-display font-bold text-2xl text-ink mt-1">{{ __('body.booking_invoice') }}</h2>
                    <p class="font-semibold text-brand mt-2">{{ $booking->booking_number }}</p>
                    <p class="text-xs text-muted mt-1">{{ __('body.generated_at') }}: {{ $generatedAt }}</p>
                </div>
                <div class="sm:text-right">
                    <x-common.status :status="$booking->status->label()" />
                    <p class="text-sm text-muted mt-2">{{ $vehicleLabel }}</p>
                </div>
            </div>

            <div class="px-5 sm:px-8 py-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted mb-3">{{ __('body.booking') }}</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                    <div>
                        <dt class="text-muted">{{ __('body.customer') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->customerName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.vehicle') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->assignedVehicle?->name() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.category') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->category?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.daily_rate') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ number_format((float) $booking->category_daily_price, 0) }} RWF/{{ __('body.day') }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.rental_type') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->rental_type?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.payment_option') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->payment_option?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.pickup_datetime') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">@kigali($booking->pickup_at)</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.return_datetime') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">@kigali($booking->return_at)</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.pickup_method') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->pickup_method?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.pickup_location') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">
                            @if ($booking->pickup_method === \App\Enums\PickupMethod::Delivery)
                                {{ $booking->delivery_address ?: '—' }}
                            @else
                                {{ $booking->pickupStation?->name ?? '—' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.return_method') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">{{ $booking->return_method?->label() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">{{ __('body.return_station') }}</dt>
                        <dd class="font-semibold text-ink mt-0.5">
                            @if ($booking->return_method === \App\Enums\ReturnMethod::Collection)
                                {{ $booking->collection_address ?: '—' }}
                            @else
                                {{ $booking->returnStation?->name ?? '—' }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($booking->extras->isNotEmpty())
                <div class="px-5 sm:px-8 pb-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted mb-3">{{ __('body.extras') }}</p>
                    <div class="space-y-2">
                        @foreach ($booking->extras as $line)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5 text-sm">
                                <span class="text-ink">{{ $line->extra?->name }} &times; {{ $line->quantity }}</span>
                                <span class="text-muted tabular-nums">{{ number_format((float) $line->total, 0) }} RWF</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="px-5 sm:px-8 py-6 border-t border-line bg-surface/50 space-y-2.5 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-muted">{{ __('body.rental_days') }} ({{ number_format((float) $booking->category_daily_price, 0) }} × {{ (int) $booking->rental_days }})</span>
                    <span class="font-semibold text-ink tabular-nums">{{ number_format((float) $rentalSubtotal, 0) }} RWF</span>
                </div>
                @if ((float) ($booking->delivery_fee ?? 0) > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">{{ __('body.delivery_fee') }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ number_format((float) $booking->delivery_fee, 0) }} RWF</span>
                    </div>
                @endif
                @if ((float) ($booking->collection_fee ?? 0) > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">{{ __('body.collection_fee') }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ number_format((float) $booking->collection_fee, 0) }} RWF</span>
                    </div>
                @endif
                @if ((float) ($booking->chauffeur_fee ?? 0) > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">{{ __('body.chauffeur_fee') }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ number_format((float) $booking->chauffeur_fee, 0) }} RWF</span>
                    </div>
                @endif
                @if ((float) ($booking->extras_total ?? 0) > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">{{ __('body.extras') }}</span>
                        <span class="font-semibold text-ink tabular-nums">{{ number_format((float) $booking->extras_total, 0) }} RWF</span>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-line">
                    <span class="font-display font-bold text-ink">{{ __('body.total') }}</span>
                    <span class="font-display font-bold text-brand text-xl tabular-nums">{{ number_format((float) $booking->total_amount, 0) }} RWF</span>
                </div>
            </div>

            <div class="px-5 sm:px-8 py-4 border-t border-line">
                <p class="text-xs text-muted leading-relaxed">{{ __('body.booking_receipt_footer', ['company' => $company]) }}</p>
            </div>
        </div>
    </div>
</x-layouts.public>
