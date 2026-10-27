@php
    $status = $booking->status;
@endphp
<x-layouts.app title="{{ $booking->booking_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$booking->booking_number"
            :subtitle="__('body.chauffeur_job_details')"
            :back-href="route('chauffeur.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$booking->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('bookings.show', $booking) }}" variant="secondary" size="sm">{{ __('body.booking_details') }}</x-common.button>
                @if (in_array($status, [\App\Enums\BookingStatus::ReadyForPickup, \App\Enums\BookingStatus::ActiveRental, \App\Enums\BookingStatus::Completed], true))
                    <x-common.button href="{{ route('rentals.show', $booking) }}" size="sm">{{ __('body.rental_details') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.customer')" :value="$booking->customerName()" />
            <x-common.details.summary-item :label="__('body.category')" :value="$booking->category?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.assigned_driver')" :value="$booking->assignedDriver?->fullName() ?? __('body.not_assigned_yet')" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$booking->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <div class="space-y-6">
            <x-common.details.section
                :title="__('body.booking_details')"
                icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            >
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.customer')">{{ $booking->customerName() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.pickup_location')">{{ $booking->pickupStation?->name ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.return_station')">{{ $booking->returnStation?->name ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.pickup_datetime')">@kigali($booking->pickup_at)</x-common.details.field>
                    <x-common.details.field :label="__('body.return_datetime')">@kigali($booking->return_at)</x-common.details.field>
                    <x-common.details.field :label="__('body.category')">{{ $booking->category?->name ?? '—' }}</x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            <x-common.details.section
                :title="__('body.assigned_driver')"
                icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
            >
                @if ($booking->assignedDriver)
                    <x-common.details.grid :cols="3">
                        <x-common.details.field :label="__('body.assigned_driver')">
                            <a href="{{ route('drivers.show', $booking->assignedDriver) }}" class="hover:text-brand">{{ $booking->assignedDriver->fullName() }}</a>
                        </x-common.details.field>
                        <x-common.details.field :label="__('body.phone')">{{ $booking->assignedDriver->phone }}</x-common.details.field>
                        <x-common.details.field :label="__('body.license_category')">{{ $booking->assignedDriver->license_category?->label() ?? '—' }}</x-common.details.field>
                    </x-common.details.grid>
                @else
                    <p class="text-sm text-muted">{{ __('body.not_assigned_yet') }} — {{ __('body.assign_driver_from_booking_note') }}</p>
                @endif
            </x-common.details.section>

            <x-common.details.section
                :title="__('body.assigned_vehicle')"
                icon="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"
            >
                @if ($booking->assignedVehicle)
                    <x-common.details.grid :cols="3">
                        <x-common.details.field :label="__('body.vehicle')">
                            <a href="{{ route('fleet.vehicles.show', $booking->assignedVehicle) }}" class="hover:text-brand">{{ $booking->assignedVehicle->name() }}</a>
                        </x-common.details.field>
                        <x-common.details.field :label="__('body.plate_number')">{{ $booking->assignedVehicle->plate_number }}</x-common.details.field>
                        <x-common.details.field :label="__('body.status')">
                            <x-common.status :status="$booking->assignedVehicle->status->label()" />
                        </x-common.details.field>
                    </x-common.details.grid>
                @else
                    <p class="text-sm text-muted">{{ __('body.not_assigned_yet') }}</p>
                @endif
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
