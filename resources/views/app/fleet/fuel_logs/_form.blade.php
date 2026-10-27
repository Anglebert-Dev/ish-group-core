@php
    $fuelLog = $fuelLog ?? null;
    $cancelHref = $cancelHref ?? route('fleet.fuel_logs.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.vehicle_and_fill')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.select id="fuel-log-vehicle" name="vehicle_id" :label="__('body.vehicle')" :value="old('vehicle_id', $fuelLog?->vehicle_id)" required placeholder="—">
            <option value="">—</option>
            @foreach ($vehicles as $v)
                <option
                    value="{{ $v->id }}"
                    data-mileage="{{ $v->mileage }}"
                    data-fuel-type="{{ $v->fuel_type?->value }}"
                    data-description="{{ $v->ownershipLabel() }}"
                    @selected((int) old('vehicle_id', $fuelLog?->vehicle_id) === $v->id)
                >{{ $v->name() }} — {{ $v->plate_number }}</option>
            @endforeach
        </x-common.select>
        <x-common.select id="fuel-log-fuel-type" name="fuel_type" :label="__('body.fuel_type')" :value="old('fuel_type', $fuelLog?->fuel_type?->value)" :options="\App\Enums\FuelType::cases()" :hide-placeholder="true" required />
        <x-common.input type="date" name="date" :label="__('body.date')" :value="old('date', $fuelLog?->date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
        <x-common.input name="gas_station_name" :label="__('body.gas_station_name')" :value="old('gas_station_name', $fuelLog?->gas_station_name)" required />
        <x-common.select name="driver_id" :label="__('body.choose_driver')" :value="old('driver_id', $fuelLog?->driver_id)" required placeholder="—">
            <option value="">—</option>
            @foreach ($drivers as $driver)
                <option value="{{ $driver->id }}" @selected((int) old('driver_id', $fuelLog?->driver_id) === $driver->id)>{{ $driver->fullName() }}</option>
            @endforeach
        </x-common.select>
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $fuelLog?->status?->value ?? 'recorded')" :options="\App\Enums\FuelLogStatus::cases()" :hide-placeholder="true" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.quantity_and_cost')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input type="number" name="liters" :label="__('body.liters')" :value="old('liters', $fuelLog?->liters)" step="0.01" min="0.01" required />
        <x-common.input amount name="cost" :label="__('body.cost').' (RWF)'" :value="old('cost', $fuelLog?->cost)" required min="0" />
        <x-common.input
            id="fuel-log-mileage"
            type="number"
            name="mileage"
            :label="__('body.mileage').' (km)'"
            :value="old('mileage', $fuelLog?->mileage)"
            min="0"
            hint="{{ __('body.fuel_log_mileage_hint') }}"
            class="sm:col-span-2"
        />
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $fuelLog?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.vehicle')" for="vehicle_id" />
        <x-common.wizard.review-row :label="__('body.fuel_type')" for="fuel_type" />
        <x-common.wizard.review-row :label="__('body.date')" for="date" />
        <x-common.wizard.review-row :label="__('body.gas_station_name')" for="gas_station_name" />
        <x-common.wizard.review-row :label="__('body.choose_driver')" for="driver_id" />
        <x-common.wizard.review-row :label="__('body.liters')" for="liters" />
        <x-common.wizard.review-row :label="__('body.cost')" for="cost" />
        <x-common.wizard.review-row :label="__('body.mileage')" for="mileage" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<script>
    document.getElementById('fuel-log-vehicle').addEventListener('change', function () {
        var mileageField = document.getElementById('fuel-log-mileage');
        var fuelTypeField = document.getElementById('fuel-log-fuel-type');
        var selected = this.options[this.selectedIndex];
        var vehicleMileage = selected.dataset.mileage;
        var vehicleFuelType = selected.dataset.fuelType;

        if (vehicleMileage && !mileageField.value) {
            mileageField.value = vehicleMileage;
        }

        if (vehicleFuelType && fuelTypeField) {
            fuelTypeField.value = vehicleFuelType;
        }
    });
</script>
