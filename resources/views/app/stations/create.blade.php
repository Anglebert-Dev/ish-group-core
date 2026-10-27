@php
    $steps = [
        ['id' => 'identity', 'label' => __('body.identity')],
        ['id' => 'hours', 'label' => __('body.hours_address')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_station') }}">
    <x-common.page-header :title="__('body.add_station')" />

    <x-common.wizard :action="route('stations.store')" :steps="$steps">
        @include('app.stations._form', ['cancelHref' => route('stations.index')])
    </x-common.wizard>
</x-layouts.app>
