@php
    $status = $booking->status;
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['id' => 'pickup', 'label' => __('body.pickup_inspection'), 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    if (in_array($status, [\App\Enums\BookingStatus::ActiveRental, \App\Enums\BookingStatus::Completed], true)) {
        $tabs[] = ['id' => 'return', 'label' => __('body.return_inspection'), 'icon' => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6'];
    }
    if ($status === \App\Enums\BookingStatus::Completed) {
        $tabs[] = ['id' => 'financial', 'label' => __('body.financial_summary'), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z'];
    }
@endphp
<x-layouts.app title="{{ $booking->booking_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$booking->booking_number"
            :subtitle="__('body.rental_details')"
            :back-href="route('rentals.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$booking->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('bookings.show', $booking) }}" variant="secondary" size="sm">{{ __('body.booking_details') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.customer')" :value="$booking->customerName()" />
            <x-common.details.summary-item :label="__('body.vehicle')" :value="($booking->assignedVehicle?->name() ?? '—').($booking->assignedVehicle ? ' — '.$booking->assignedVehicle->plate_number : '')" />
            <x-common.details.summary-item :label="__('body.assigned_driver')" :value="$booking->assignedDriver?->fullName() ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$booking->status->label()" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" active="overview" />
            </x-slot:tabs>
        </x-common.details.summary>

        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.booking_details')" icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.customer')">{{ $booking->customerName() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.vehicle')">{{ $booking->assignedVehicle?->name() }} — {{ $booking->assignedVehicle?->plate_number }}</x-common.details.field>
                    <x-common.details.field :label="__('body.assigned_driver')">{{ $booking->assignedDriver?->fullName() ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.pickup_datetime')">@kigali($booking->pickup_at)</x-common.details.field>
                    <x-common.details.field :label="__('body.return_datetime')">@kigali($booking->return_at)</x-common.details.field>
                    @if ($booking->actual_return_at)
                        <x-common.details.field :label="__('body.actual_return_datetime')">@kigali($booking->actual_return_at)</x-common.details.field>
                    @endif
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="pickup" class="hidden space-y-6">
            <x-common.details.section :title="__('body.pickup_inspection')" icon="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" tone="success">
                @if ($booking->pickupInspection)
                    <x-common.details.grid :cols="3" class="mb-4">
                        <x-common.details.field :label="__('body.mileage')">{{ number_format($booking->pickupInspection->mileage) }} km</x-common.details.field>
                        <x-common.details.field :label="__('body.fuel_level')">{{ $booking->pickupInspection->fuel_level->label() }}</x-common.details.field>
                        <x-common.details.field :label="__('body.deposit_amount')">{{ number_format($booking->pickupInspection->deposit_amount ?? 0, 0) }} RWF</x-common.details.field>
                    </x-common.details.grid>
                    @if ($booking->pickupInspection->condition_notes)
                        <p class="text-sm text-body mb-4">{{ $booking->pickupInspection->condition_notes }}</p>
                    @endif
                    @if ($booking->pickupInspection->photos->isNotEmpty())
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                            @foreach ($booking->pickupInspection->photos as $photo)
                                <img src="{{ $photo->url() }}" class="w-full h-20 object-cover rounded-lg border border-line" alt="">
                            @endforeach
                        </div>
                    @endif
                @elseif ($status === \App\Enums\BookingStatus::ReadyForPickup)
                    <p class="text-sm text-muted mb-4">{{ __('body.awaiting_pickup') }}</p>
                    <form action="{{ route('rentals.pickup', $booking) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <x-common.input type="number" name="mileage" :label="__('body.mileage')" min="0" required />
                            <x-common.select name="fuel_level" :label="__('body.fuel_level')" :options="\App\Enums\FuelLevel::cases()" :hide-placeholder="true" required />
                            <div class="flex min-w-0 flex-col gap-2 w-full">
                                <label class="text-sm font-semibold text-ink">{{ __('body.deposit_amount') }}</label>
                                <p class="h-12 flex items-center px-4 text-sm text-ink bg-surface border border-line rounded-xl">{{ number_format($booking->total_amount, 0) }} RWF</p>
                                <p class="text-xs text-muted">{{ __('body.deposit_equals_total_note') }}</p>
                            </div>
                        </div>
                        <x-common.textarea name="condition_notes" :label="__('body.condition_notes')" :rows="2" />
                        <x-common.file-input name="photos[]" :label="__('body.photos')" accept="image/*" :multiple="true" :max-size-mb="4" />
                        <x-common.button type="submit" size="sm">{{ __('body.record_pickup') }}</x-common.button>
                    </form>
                @else
                    <p class="text-sm text-muted">—</p>
                @endif
            </x-common.details.section>
        </div>

        @if (in_array($status, [\App\Enums\BookingStatus::ActiveRental, \App\Enums\BookingStatus::Completed], true))
            <div data-details-panel="return" class="hidden space-y-6">
                <x-common.details.section :title="__('body.return_inspection')" icon="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" tone="warning">
                    @if ($booking->returnInspection)
                        <x-common.details.grid :cols="3" class="mb-4">
                            <x-common.details.field :label="__('body.mileage')">{{ number_format($booking->returnInspection->mileage) }} km</x-common.details.field>
                            <x-common.details.field :label="__('body.fuel_level')">{{ $booking->returnInspection->fuel_level->label() }}</x-common.details.field>
                            <x-common.details.field :label="__('body.damage_charges')">{{ number_format($booking->returnInspection->damage_charges ?? 0, 0) }} RWF</x-common.details.field>
                        </x-common.details.grid>
                        @if ($booking->returnInspection->condition_notes)
                            <p class="text-sm text-body mb-4">{{ $booking->returnInspection->condition_notes }}</p>
                        @endif
                        @if ($booking->returnInspection->photos->isNotEmpty())
                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                                @foreach ($booking->returnInspection->photos as $photo)
                                    <img src="{{ $photo->url() }}" class="w-full h-20 object-cover rounded-lg border border-line" alt="">
                                @endforeach
                            </div>
                        @endif
                    @elseif ($status === \App\Enums\BookingStatus::ActiveRental)
                        @php $pickupMileage = (int) ($booking->pickupInspection?->mileage ?? 0); @endphp
                        <p class="text-sm text-muted mb-4">{{ __('body.awaiting_return') }}</p>
                        <form action="{{ route('rentals.return', $booking) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <x-common.input
                                    type="number"
                                    name="mileage"
                                    :label="__('body.mileage')"
                                    :min="$pickupMileage"
                                    :value="old('mileage')"
                                    required
                                    :hint="$pickupMileage > 0 ? __('body.return_mileage_pickup_hint', ['mileage' => number_format($pickupMileage)]) : null"
                                />
                                <x-common.select name="fuel_level" :label="__('body.fuel_level')" :options="\App\Enums\FuelLevel::cases()" :hide-placeholder="true" required />
                                <x-common.input amount name="damage_charges" :label="__('body.damage_charges')" min="0" />
                            </div>
                            <x-common.textarea name="condition_notes" :label="__('body.condition_notes')" :rows="2" />
                            <x-common.file-input name="photos[]" :label="__('body.photos')" accept="image/*" :multiple="true" :max-size-mb="4" />
                            <x-common.button type="submit" size="sm">{{ __('body.record_return') }}</x-common.button>
                        </form>
                    @endif
                </x-common.details.section>
            </div>
        @endif

        @if ($status === \App\Enums\BookingStatus::Completed)
            <div data-details-panel="financial" class="hidden space-y-6">
                <x-common.details.section :title="__('body.financial_summary')" icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z" tone="success">
                    <x-common.details.grid :cols="3">
                        <x-common.details.field :label="__('body.total_amount')">{{ number_format($booking->total_amount, 0) }} RWF</x-common.details.field>
                        <x-common.details.field :label="__('body.extra_days_charge')">{{ number_format($booking->extra_days_charge ?? 0, 0) }} RWF</x-common.details.field>
                        <x-common.details.field :label="__('body.damage_charges')">{{ number_format($booking->returnInspection?->damage_charges ?? 0, 0) }} RWF</x-common.details.field>
                        <x-common.details.field :label="__('body.deposit_amount')">{{ number_format($booking->pickupInspection?->deposit_amount ?? 0, 0) }} RWF</x-common.details.field>
                        <x-common.details.field :label="($booking->outstanding_balance ?? 0) < 0 ? __('body.refund_due') : __('body.outstanding_balance')" :span="2">
                            @if (($booking->outstanding_balance ?? 0) < 0)
                                <span class="text-success text-xl font-display font-bold">{{ number_format(abs($booking->outstanding_balance), 0) }} RWF</span>
                            @else
                                <span class="text-xl font-display font-bold">{{ number_format($booking->outstanding_balance ?? 0, 0) }} RWF</span>
                            @endif
                        </x-common.details.field>
                    </x-common.details.grid>
                </x-common.details.section>
            </div>
        @endif
    </div>
</x-layouts.app>
