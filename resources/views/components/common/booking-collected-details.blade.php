@props([
    'booking',
    'variant' => 'public', // public | staff
])

@php
    /** @var \App\Models\Booking\Booking $booking */
    $isStaff = $variant === 'staff';
    $rentalSubtotal = bcmul((string) ($booking->category_daily_price ?? 0), (string) max(1, (int) $booking->rental_days), 2);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-5']) }}>
    <x-common.details.grid :cols="3">
        @if ($isStaff)
            <x-common.details.field :label="__('body.customer_type')">
                {{ $booking->isCorporate() ? __('body.corporate') : __('body.individual') }}
            </x-common.details.field>
            <x-common.details.field :label="__('body.customer')">
                {{ $booking->customerName() }}
            </x-common.details.field>
        @endif

        @if ($booking->assignedVehicle)
            <x-common.details.field :label="__('body.vehicle')">
                {{ $booking->assignedVehicle->name() }}
            </x-common.details.field>
        @endif

        <x-common.details.field :label="__('body.category')">
            {{ $booking->category?->name ?? '—' }}
        </x-common.details.field>

        <x-common.details.field :label="__('body.daily_rate')">
            {{ number_format((float) $booking->category_daily_price, 0) }} RWF/{{ __('body.day') }}
        </x-common.details.field>

        <x-common.details.field :label="__('body.rental_days')">
            {{ $booking->rental_days }}
        </x-common.details.field>

        <x-common.details.field :label="__('body.rental_type')">
            {{ $booking->rental_type?->label() ?? '—' }}
        </x-common.details.field>

        <x-common.details.field :label="__('body.pickup_method')">
            {{ $booking->pickup_method?->label() ?? __('body.pickup_method_station') }}
        </x-common.details.field>

        <x-common.details.field :label="$booking->pickup_method === \App\Enums\PickupMethod::Delivery ? __('body.delivery_address') : __('body.pickup_location')">
            @if ($booking->pickup_method === \App\Enums\PickupMethod::Delivery)
                {{ $booking->delivery_address ?: '—' }}
            @else
                {{ $booking->pickupStation?->name ?? '—' }}
            @endif
        </x-common.details.field>

        <x-common.details.field :label="__('body.return_method')">
            {{ $booking->return_method?->label() ?? __('body.return_method_station') }}
        </x-common.details.field>

        <x-common.details.field :label="$booking->return_method === \App\Enums\ReturnMethod::Collection ? __('body.collection_address') : __('body.return_station')">
            @if ($booking->return_method === \App\Enums\ReturnMethod::Collection)
                {{ $booking->collection_address ?: '—' }}
            @else
                {{ $booking->returnStation?->name ?? '—' }}
            @endif
        </x-common.details.field>

        @if ($booking->pickup_method === \App\Enums\PickupMethod::Delivery && $booking->pickupStation)
            <x-common.details.field :label="__('body.home_station')">
                {{ $booking->pickupStation->name }}
            </x-common.details.field>
        @endif

        @if ($booking->return_method === \App\Enums\ReturnMethod::Collection && $booking->returnStation)
            <x-common.details.field :label="__('body.return_home_station')">
                {{ $booking->returnStation->name }}
            </x-common.details.field>
        @endif

        <x-common.details.field :label="__('body.pickup_datetime')">
            @kigali($booking->pickup_at)
        </x-common.details.field>

        <x-common.details.field :label="__('body.return_datetime')">
            @kigali($booking->return_at)
        </x-common.details.field>

        @if ($booking->rental_type === \App\Enums\RentalType::SelfDrive)
            <x-common.details.field :label="__('body.license_number')">
                {{ $booking->driver_license_number ?: '—' }}
            </x-common.details.field>
            <x-common.details.field :label="__('body.driver_license_category')">
                {{ $booking->driver_license_category?->label() ?? '—' }}
            </x-common.details.field>
        @endif

        <x-common.details.field :label="__('body.payment_option')">
            <div class="space-y-1">
                <p>{{ $booking->payment_option?->label() ?? '—' }}</p>
                @if ($booking->isPayLater())
                    <x-common.badge color="warning">{{ __('body.pay_later_badge') }}</x-common.badge>
                @endif
                @if ($booking->payment_option?->hint())
                    <p class="text-xs text-muted">{{ $booking->payment_option->hint() }}</p>
                @endif
            </div>
        </x-common.details.field>
    </x-common.details.grid>

    <div class="pt-4 border-t border-line space-y-3">
        <p class="text-xs font-semibold text-muted uppercase tracking-wide">
            {{ __('body.payment_summary') }}
        </p>

        <div class="space-y-1.5">
            <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg px-3 py-2 bg-surface">
                <span class="text-ink">
                    {{ __('body.vehicle_rental') }}
                    <span class="text-muted">
                        ({{ number_format((float) $booking->category_daily_price, 0) }} × {{ $booking->rental_days }} {{ (int) $booking->rental_days === 1 ? __('body.day') : __('body.days') }})
                    </span>
                </span>
                <span class="text-muted tabular-nums shrink-0">{{ number_format((float) $rentalSubtotal, 0) }} RWF</span>
            </div>

            @if ((float) ($booking->delivery_fee ?? 0) > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg px-3 py-2 bg-surface">
                    <span class="text-ink">{{ __('body.delivery_fee') }}</span>
                    <span class="text-muted tabular-nums shrink-0">{{ number_format((float) $booking->delivery_fee, 0) }} RWF</span>
                </div>
            @endif

            @if ((float) ($booking->collection_fee ?? 0) > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg px-3 py-2 bg-surface">
                    <span class="text-ink">{{ __('body.collection_fee') }}</span>
                    <span class="text-muted tabular-nums shrink-0">{{ number_format((float) $booking->collection_fee, 0) }} RWF</span>
                </div>
            @endif

            @if ((float) ($booking->chauffeur_fee ?? 0) > 0)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg px-3 py-2 bg-surface">
                    <span class="text-ink">{{ __('body.chauffeur_fee_line', ['days' => $booking->rental_days]) }}</span>
                    <span class="text-muted tabular-nums shrink-0">{{ number_format((float) $booking->chauffeur_fee, 0) }} RWF</span>
                </div>
            @endif

            @forelse ($booking->extras as $line)
                <div class="flex items-center justify-between gap-3 text-sm border border-line rounded-lg px-3 py-2 bg-surface">
                    <span class="text-ink">{{ $line->extra?->name ?? __('body.extras') }} × {{ $line->quantity }}</span>
                    <span class="text-muted tabular-nums shrink-0">{{ number_format((float) $line->total, 0) }} RWF</span>
                </div>
            @empty
            @endforelse
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <p class="text-sm font-semibold text-ink">{{ __('body.total_amount') }}</p>
            <p class="font-display font-bold text-xl text-ink tabular-nums">{{ number_format((float) $booking->total_amount, 0) }} RWF</p>
        </div>
    </div>

    @if (filled($booking->notes))
        <x-common.details.field :label="__('body.notes')">
            <span class="whitespace-pre-line">{{ $booking->notes }}</span>
        </x-common.details.field>
    @endif
</div>
