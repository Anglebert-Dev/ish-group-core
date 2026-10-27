@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.edit_expense') }}">
    <x-common.page-header :title="__('body.edit_expense')" />
    <x-common.wizard :action="route('finance.expenses.update', $expense)" method="PUT" :steps="$steps">
        @include('app.finance.expenses._form', ['expense' => $expense, 'suppliers' => $suppliers, 'accounts' => $accounts])
    </x-common.wizard>
</x-layouts.app>
