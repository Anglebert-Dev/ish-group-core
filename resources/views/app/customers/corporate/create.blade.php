@php
    $steps = [
        ['id' => 'company', 'label' => __('body.company')],
        ['id' => 'contacts', 'label' => __('body.contacts')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_corporate_customer') }}">
    <x-common.page-header :title="__('body.add_corporate_customer')" />

    <x-common.wizard :action="route('customers.corporate.store')" :steps="$steps">
        @include('app.customers.corporate._form', ['cancelHref' => route('customers.corporate.index')])
    </x-common.wizard>
</x-layouts.app>
