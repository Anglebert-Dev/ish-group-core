@php
    $steps = [
        ['id' => 'vehicle', 'label' => __('body.vehicle_and_type')],
        ['id' => 'cost', 'label' => __('body.cost_notes')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_maintenance') }}">
    <x-common.page-header :title="__('body.edit_maintenance')" :description="$maintenance->vehicle?->name()" />

    <x-common.wizard :action="route('fleet.maintenance.update', $maintenance)" method="PUT" :steps="$steps">
        @include('app.fleet.maintenance._form', ['cancelHref' => route('fleet.maintenance.index')])
    </x-common.wizard>
</x-layouts.app>
