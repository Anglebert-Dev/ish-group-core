@php
    $steps = [
        ['id' => 'basic', 'label' => __('body.basic_information')],
        ['id' => 'ownership', 'label' => __('body.ownership')],
        ['id' => 'specs', 'label' => __('body.specifications')],
        ['id' => 'ops', 'label' => __('body.operational_information')],
        ['id' => 'compliance', 'label' => __('body.compliance_documents')],
        ['id' => 'images', 'label' => __('body.images')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_vehicle') }}">
    <x-common.page-header :title="__('body.add_vehicle')" />

    <x-common.wizard :action="route('fleet.vehicles.store')" :steps="$steps" enctype="multipart/form-data">
        @include('app.fleet.vehicles._form', ['cancelHref' => route('fleet.vehicles.index')])
    </x-common.wizard>
</x-layouts.app>
