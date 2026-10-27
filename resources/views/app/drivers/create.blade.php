@php
    $steps = [
        ['id' => 'identity', 'label' => __('body.identity')],
        ['id' => 'license', 'label' => __('body.license_employment')],
        ['id' => 'docs', 'label' => __('body.documents')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_driver') }}">
    <x-common.page-header :title="__('body.add_driver')" />

    <x-common.wizard :action="route('drivers.store')" :steps="$steps" enctype="multipart/form-data">
        @include('app.drivers._form', ['cancelHref' => route('drivers.index')])
    </x-common.wizard>
</x-layouts.app>
