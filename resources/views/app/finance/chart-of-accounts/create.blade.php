@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_account') }}">
    <x-common.page-header :title="__('body.add_account')" />
    <x-common.wizard :action="route('finance.chart-of-accounts.store')" :steps="$steps">
        @include('app.finance.chart-of-accounts._form', ['parents' => $parents, 'cancelHref' => route('finance.chart-of-accounts.index')])
    </x-common.wizard>
</x-layouts.app>
