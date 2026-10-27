@php
    $steps = [
        ['id' => 'identity', 'label' => __('body.identity')],
        ['id' => 'license', 'label' => __('body.license_employment')],
        ['id' => 'docs', 'label' => __('body.documents')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_driver') }}">
    <x-common.page-header :title="__('body.edit_driver')" :description="$driver->fullName()" />

    <x-common.wizard :action="route('drivers.update', $driver)" method="PUT" :steps="$steps" enctype="multipart/form-data">
        @include('app.drivers._form', ['cancelHref' => route('drivers.index')])
    </x-common.wizard>

    @stack('driver-document-delete-forms')
</x-layouts.app>
