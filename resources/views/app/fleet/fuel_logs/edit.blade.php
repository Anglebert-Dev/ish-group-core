@php
    $steps = [
        ['id' => 'vehicle', 'label' => __('body.vehicle_and_fill')],
        ['id' => 'quantity', 'label' => __('body.quantity_and_cost')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_fuel_log') }}">
    <x-common.page-header :title="__('body.edit_fuel_log')" :description="$fuelLog->vehicle?->name()" />

    <x-common.wizard :action="route('fleet.fuel_logs.update', $fuelLog)" method="PUT" :steps="$steps">
        @include('app.fleet.fuel_logs._form', ['cancelHref' => route('fleet.fuel_logs.index')])
    </x-common.wizard>
</x-layouts.app>
