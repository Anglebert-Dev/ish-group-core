@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_extra') }}">
    <x-common.page-header :title="__('body.add_extra')" />

    <x-common.wizard :action="route('extras.store')" :steps="$steps">
        @include('app.extras._form', ['cancelHref' => route('extras.index')])
    </x-common.wizard>
</x-layouts.app>
