@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_expense') }}">
    <x-common.page-header :title="__('body.add_expense')" />
    <x-common.wizard :action="route('finance.expenses.store')" :steps="$steps">
        @include('app.finance.expenses._form', ['suppliers' => $suppliers, 'accounts' => $accounts])
    </x-common.wizard>
</x-layouts.app>
