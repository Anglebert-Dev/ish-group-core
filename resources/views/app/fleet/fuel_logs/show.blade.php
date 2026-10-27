<x-layouts.app title="{{ $fuelLog->vehicle?->name() ?? __('body.fuel_logs') }}">
    <div data-details-page>
        <x-common.details.header
            :title="$fuelLog->vehicle?->name() ?? __('body.fuel_logs')"
            :subtitle="__('body.fuel_log_details')"
            :back-href="route('fleet.fuel_logs.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$fuelLog->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('fleet.fuel_logs.edit', $fuelLog) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.date')" :value="$fuelLog->date->format('d M Y')" />
            <x-common.details.summary-item :label="__('body.liters')" :value="number_format($fuelLog->liters, 2).' L'" />
            <x-common.details.summary-item :label="__('body.cost')" :value="number_format($fuelLog->cost, 2).' RWF'" />
            <x-common.details.summary-item :label="__('body.fuel_type')" :value="$fuelLog->fuel_type->label()" />
        </x-common.details.summary>

        <x-common.status-update
            :action="route('fleet.fuel_logs.status', $fuelLog)"
            :value="$fuelLog->status->value"
            :options="\App\Enums\FuelLogStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update fleet.fuel_log')"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.vehicle')">
                    @if ($fuelLog->vehicle)
                        <a href="{{ route('fleet.vehicles.show', $fuelLog->vehicle) }}" class="text-brand hover:text-brand-dark">{{ $fuelLog->vehicle->name() }}</a>
                    @else
                        —
                    @endif
                </x-common.details.field>
                <x-common.details.field :label="__('body.gas_station_name')">{{ $fuelLog->gas_station_name ?: '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.driver')">
                    @if ($fuelLog->driver)
                        <a href="{{ route('drivers.show', $fuelLog->driver) }}" class="text-brand hover:text-brand-dark">{{ $fuelLog->driver->fullName() }}</a>
                    @else
                        —
                    @endif
                </x-common.details.field>
                <x-common.details.field :label="__('body.fuel_type')">{{ $fuelLog->fuel_type->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$fuelLog->status->label()" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.date')">{{ $fuelLog->date->format('d M Y') }}</x-common.details.field>
                <x-common.details.field :label="__('body.mileage')">{{ $fuelLog->mileage !== null ? number_format($fuelLog->mileage).' km' : '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.liters')">{{ number_format($fuelLog->liters, 2) }} L</x-common.details.field>
                <x-common.details.field :label="__('body.cost')">{{ number_format($fuelLog->cost, 2) }} RWF</x-common.details.field>
                <x-common.details.field :label="__('body.notes')" :span="2">
                    <span class="whitespace-pre-line">{{ $fuelLog->notes ?? '—' }}</span>
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
