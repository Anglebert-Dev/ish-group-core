@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'image', 'label' => __('body.image_step')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_category') }}">
    <x-common.page-header :title="__('body.add_category')" />

    <x-common.wizard :action="route('fleet.categories.store')" :steps="$steps" enctype="multipart/form-data">
        @include('app.fleet.categories._form', ['cancelHref' => route('fleet.categories.index')])
    </x-common.wizard>
</x-layouts.app>
