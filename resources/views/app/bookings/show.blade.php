@php
    /** @var \App\Models\Booking\Booking $booking */
    $status = $booking->status;
    $canCancel = in_array($status, [\App\Enums\BookingStatus::Pending, \App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::ReadyForPickup], true);
    $canNoShow = in_array($status, [\App\Enums\BookingStatus::Confirmed, \App\Enums\BookingStatus::ReadyForPickup], true);
    $canExtend = $canExtend ?? false;
    $showActions = $canCancel || $canNoShow || $canExtend || $status === \App\Enums\BookingStatus::Pending;
    $tabs = [
        ['id' => 'overview', 'label' => __('body.overview'), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['id' => 'assignment', 'label' => __('body.assignment'), 'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3'],
    ];
    if ($showActions) {
        $tabs[] = ['id' => 'actions', 'label' => __('body.actions'), 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'];
    }
@endphp
<x-layouts.app title="{{ $booking->booking_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$booking->booking_number"
            :subtitle="__('body.booking_details')"
            :back-href="route('bookings.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$booking->status->label()" />
                @if ($booking->isPayLater())
                    <x-common.badge color="warning">{{ __('body.pay_later_badge') }}</x-common.badge>
                @endif
            </x-slot:meta>
            <x-slot:actions>
                @if (in_array($status, [\App\Enums\BookingStatus::ReadyForPickup, \App\Enums\BookingStatus::ActiveRental, \App\Enums\BookingStatus::Completed], true))
                    <x-common.button href="{{ route('rentals.show', $booking) }}" size="sm">{{ __('body.rental_details') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.customer')" :value="$booking->customerName()" />
            <x-common.details.summary-item :label="__('body.vehicle')" :value="$booking->assignedVehicle?->name() ?? $booking->category?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.daily_rate')" :value="number_format((float) $booking->category_daily_price, 0).' RWF/'.__('body.day')" />
            <x-common.details.summary-item :label="__('body.total_amount')" :value="number_format((float) $booking->total_amount, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$booking->status->label()" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" active="overview" />
            </x-slot:tabs>
        </x-common.details.summary>

        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.booking_details')" icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                <x-common.booking-collected-details :booking="$booking" variant="staff" />

                @if ($booking->status === \App\Enums\BookingStatus::Cancelled && $booking->cancellation_reason)
                    <div class="mt-5 pt-4 border-t border-line">
                        <x-common.details.field :label="__('body.cancellation_reason')">
                            <span class="whitespace-pre-line">{{ $booking->cancellation_reason }}</span>
                        </x-common.details.field>
                    </div>
                @endif
            </x-common.details.section>
        </div>

        <div data-details-panel="assignment" class="hidden space-y-6">
            <x-common.details.section :title="__('body.assign_vehicle').' / '.__('body.assign_driver')" icon="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3">
                <x-common.details.grid class="mb-5">
                    <x-common.details.field :label="__('body.assigned_vehicle')">
                        {{ $booking->assignedVehicle?->name() ?? __('body.not_assigned_yet') }}
                    </x-common.details.field>
                    @if ($booking->rental_type === \App\Enums\RentalType::Chauffeur)
                        <x-common.details.field :label="__('body.assigned_driver')">
                            {{ $booking->assignedDriver?->fullName() ?? __('body.not_assigned_yet') }}
                        </x-common.details.field>
                    @endif
                </x-common.details.grid>

                @if ($status === \App\Enums\BookingStatus::Confirmed)
                    @if (! $booking->assigned_vehicle_id)
                        <form action="{{ route('bookings.assign-vehicle', $booking) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
                            @csrf
                            <x-common.select name="vehicle_id" :label="__('body.assign_vehicle')" required placeholder="—" class="w-full sm:flex-1 min-w-0">
                                <option value="">—</option>
                                @foreach ($availableVehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->name() }} — {{ $vehicle->plate_number }} ({{ number_format((float) ($vehicle->dailyPrice() ?? 0), 0) }} RWF/{{ __('body.day') }})</option>
                                @endforeach
                            </x-common.select>
                            <x-common.button type="submit" size="sm">{{ __('body.assign_vehicle') }}</x-common.button>
                        </form>
                        @if ($availableVehicles->isEmpty())
                            <p class="text-sm text-muted mt-3">{{ __('body.no_vehicles_available_for_assignment') }}</p>
                        @endif
                    @elseif ($booking->rental_type === \App\Enums\RentalType::Chauffeur && ! $booking->assigned_driver_id)
                        <form action="{{ route('bookings.assign-driver', $booking) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3">
                            @csrf
                            <x-common.select name="driver_id" :label="__('body.assign_driver')" required placeholder="—" class="w-full sm:flex-1 min-w-0">
                                <option value="">—</option>
                                @foreach ($availableDrivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->fullName() }} ({{ $driver->license_category?->label() }})</option>
                                @endforeach
                            </x-common.select>
                            <x-common.button type="submit" size="sm">{{ __('body.assign_driver') }}</x-common.button>
                        </form>
                        @if ($availableDrivers->isEmpty())
                            <p class="text-sm text-muted mt-3">{{ __('body.no_drivers_available_for_assignment') }}</p>
                        @endif
                    @else
                        <form action="{{ route('bookings.ready-for-pickup', $booking) }}" method="POST">
                            @csrf
                            <x-common.button type="submit" size="sm">{{ __('body.mark_ready_for_pickup') }}</x-common.button>
                        </form>
                    @endif
                @endif
            </x-common.details.section>
        </div>

        @if ($showActions)
            <div data-details-panel="actions" class="hidden space-y-6">
                @if ($canExtend)
                    <x-common.details.section :title="__('body.extend_booking_staff')" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" tone="brand">
                        <p class="text-xs text-muted mb-4">{{ __('body.extend_booking_hint', [
                            'daily' => number_format((float) $booking->category_daily_price, 0),
                            'surcharge' => number_format((float) ($extensionDailySurcharge ?? 0), 0),
                        ]) }}</p>
                        <form action="{{ route('bookings.extend', $booking) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-3 items-end">
                            @csrf
                            <x-common.datetime-split
                                name="new_return_at"
                                :label="__('body.new_return_datetime')"
                                :date-placeholder="__('body.hero_return_date')"
                                :value="old('new_return_at')"
                                required
                                :min="$booking->return_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i')"
                            />
                            <x-common.button type="submit" class="w-full sm:w-auto">{{ __('body.request_extension') }}</x-common.button>
                        </form>
                    </x-common.details.section>
                @endif

                <x-common.details.section :title="__('body.status')" icon="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" tone="warning">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($status === \App\Enums\BookingStatus::Pending)
                            <form action="{{ route('bookings.confirm', $booking) }}" method="POST">
                                @csrf
                                <x-common.button type="submit" size="sm">{{ __('body.confirm_booking') }}</x-common.button>
                            </form>
                        @endif

                        @if ($canNoShow)
                            <form action="{{ route('bookings.no-show', $booking) }}" method="POST">
                                @csrf
                                <x-common.button type="submit" variant="secondary" size="sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.mark_no_show') }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.mark_no_show') }}"
                                >{{ __('body.mark_no_show') }}</x-common.button>
                            </form>
                        @endif
                    </div>

                    @if ($canCancel)
                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST" class="mt-5 pt-5 border-t border-line space-y-3">
                            @csrf
                            <x-common.textarea name="reason" :label="__('body.cancellation_reason')" :rows="2" />
                            <x-common.button type="submit" variant="danger" size="sm"
                                data-confirm
                                data-confirm-title="{{ __('body.cancel_booking') }}?"
                                data-confirm-variant="danger"
                                data-confirm-text="{{ __('body.cancel_booking') }}"
                            >{{ __('body.cancel_booking') }}</x-common.button>
                        </form>
                    @endif
                </x-common.details.section>
            </div>
        @endif
    </div>
</x-layouts.app>
