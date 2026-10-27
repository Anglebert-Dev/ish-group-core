@php
    $steps = [
        ['id' => 'identity', 'label' => __('body.identity')],
        ['id' => 'agreement', 'label' => __('body.income_agreement')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_vehicle_owner') }}">
    <x-common.page-header :title="__('body.add_vehicle_owner')" />

    <x-common.wizard :action="route('fleet.owners.store')" :steps="$steps">
        @include('app.fleet.owners._form', ['cancelHref' => route('fleet.owners.index')])
    </x-common.wizard>
</x-layouts.app>
