@php
    $maintenance = $maintenance ?? null;
    $cancelHref = $cancelHref ?? route('fleet.maintenance.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.vehicle_and_type')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.select id="maintenance-vehicle" name="vehicle_id" :label="__('body.vehicle')" :value="old('vehicle_id', $maintenance?->vehicle_id)" required placeholder="—">
            <option value="">—</option>
            @foreach ($vehicles as $v)
                <option
                    value="{{ $v->id }}"
                    data-mileage="{{ $v->mileage }}"
                    data-description="{{ $v->ownershipLabel() }}"
                    @selected((int) old('vehicle_id', $maintenance?->vehicle_id) === $v->id)
                >{{ $v->name() }} — {{ $v->plate_number }}</option>
            @endforeach
        </x-common.select>
        <x-common.select name="maintenance_type" :label="__('body.maintenance_type')" :value="old('maintenance_type', $maintenance?->maintenance_type?->value)" :options="\App\Enums\MaintenanceType::cases()" :hide-placeholder="true" />
        <x-common.input type="date" name="date" label="Date" :value="old('date', $maintenance?->date?->format('Y-m-d'))" required />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $maintenance?->status?->value)" :options="\App\Enums\MaintenanceStatus::cases()" :hide-placeholder="true" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.cost_notes')">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input amount name="cost" :label="__('body.cost').' (RWF)'" :value="old('cost', $maintenance?->cost)" required min="0" />
        <x-common.input
            id="maintenance-mileage"
            type="number"
            name="mileage"
            :label="__('body.mileage').' (km)'"
            :value="old('mileage', $maintenance?->mileage)"
            min="0"
            hint="Pre-filled from the vehicle's current mileage when you pick it — adjust if the actual reading differs."
        />
        <x-common.textarea name="description" :label="__('body.description')" :value="old('description', $maintenance?->description)" :rows="2" class="sm:col-span-2" />
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $maintenance?->notes)" :rows="2" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.vehicle')" for="vehicle_id" />
        <x-common.wizard.review-row :label="__('body.maintenance_type')" for="maintenance_type" />
        <x-common.wizard.review-row label="Date" for="date" />
        <x-common.wizard.review-row :label="__('body.cost')" for="cost" />
        <x-common.wizard.review-row :label="__('body.mileage')" for="mileage" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<script>
    document.getElementById('maintenance-vehicle').addEventListener('change', function () {
        var mileageField = document.getElementById('maintenance-mileage');
        var selected = this.options[this.selectedIndex];
        var vehicleMileage = selected.dataset.mileage;

        if (vehicleMileage && !mileageField.value) {
            mileageField.value = vehicleMileage;
        }
    });
</script>
