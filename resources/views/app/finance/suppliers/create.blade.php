@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_supplier') }}">
    <x-common.page-header :title="__('body.add_supplier')" />

    <x-common.wizard :action="route('finance.suppliers.store')" :steps="$steps">
        @include('app.finance.suppliers._form', ['cancelHref' => route('finance.suppliers.index')])
    </x-common.wizard>
</x-layouts.app>
