@php
    $steps = [
        ['id' => 'vehicle', 'label' => __('body.vehicle_and_fill')],
        ['id' => 'quantity', 'label' => __('body.quantity_and_cost')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_fuel_log') }}">
    <x-common.page-header :title="__('body.add_fuel_log')" />

    <x-common.wizard :action="route('fleet.fuel_logs.store')" :steps="$steps">
        @include('app.fleet.fuel_logs._form', ['cancelHref' => route('fleet.fuel_logs.index')])
    </x-common.wizard>
</x-layouts.app>
