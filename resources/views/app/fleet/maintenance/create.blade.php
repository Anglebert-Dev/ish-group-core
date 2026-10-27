@php
    $steps = [
        ['id' => 'vehicle', 'label' => __('body.vehicle_and_type')],
        ['id' => 'cost', 'label' => __('body.cost_notes')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_maintenance') }}">
    <x-common.page-header :title="__('body.add_maintenance')" />

    <x-common.wizard :action="route('fleet.maintenance.store')" :steps="$steps">
        @include('app.fleet.maintenance._form', ['cancelHref' => route('fleet.maintenance.index')])
    </x-common.wizard>
</x-layouts.app>
