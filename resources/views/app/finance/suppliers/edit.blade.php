@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_supplier') }}">
    <x-common.page-header :title="__('body.edit_supplier')" :description="$supplier->name" />

    <x-common.wizard :action="route('finance.suppliers.update', $supplier)" method="PUT" :steps="$steps">
        @include('app.finance.suppliers._form', ['cancelHref' => route('finance.suppliers.index')])
    </x-common.wizard>
</x-layouts.app>
