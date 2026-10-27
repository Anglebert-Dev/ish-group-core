@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_account') }}">
    <x-common.page-header :title="__('body.edit_account')" />
    <x-common.wizard :action="route('finance.chart-of-accounts.update', $account)" method="PUT" :steps="$steps">
        @include('app.finance.chart-of-accounts._form', ['account' => $account, 'parents' => $parents, 'cancelHref' => route('finance.chart-of-accounts.index')])
    </x-common.wizard>
</x-layouts.app>
