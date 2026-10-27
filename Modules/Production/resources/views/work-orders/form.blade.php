@php $isNew = ! $workOrder->exists; $started = ! $isNew && array_sum($workOrder->counts) > 0; @endphp
<x-layouts.app :title="$isNew ? __('production::production.add') : $workOrder->number">
    <x-common.page-header :title="$isNew ? __('production::production.add') : __('production::production.edit')" :description="$isNew ? implode(' → ', $stages) : $workOrder->number">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('production.work-orders.index') : route('production.work-orders.show', $workOrder) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('production.work-orders.store') : route('production.work-orders.update', $workOrder) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-4xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        @if ($started)
            <input type="hidden" name="variant" value="{{ $workOrder->variant?->uuid }}">
            <input type="hidden" name="store" value="{{ $workOrder->store?->uuid }}">
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.select name="variant" :label="__('production::production.product')" :value="old('variant', $workOrder->variant?->uuid)" :options="$products" searchable required :disabled="$started" />
            <x-common.input name="quantity" type="number" step="any" min="0" :label="__('production::production.quantity')" :value="old('quantity', $workOrder->quantity)" required />
            <x-common.select name="store" :label="__('production::production.store')" :value="old('store', $workOrder->store?->uuid ?? $defaultStore)" :options="$stores" hide-placeholder required :disabled="$started" />
            @if ($salesOrders !== null)
                <x-common.select name="sales_order" :label="__('production::production.sales_order')" :value="old('sales_order', $selectedSalesOrder)" :options="['' => __('production::production.sales_order_none')] + $salesOrders" hide-placeholder searchable />
            @endif
            <x-common.input name="line" :label="__('production::production.line')" :value="old('line', $workOrder->line)" :hint="__('production::production.line_hint')" list="production-lines" />
            <x-common.input name="daily_target" type="number" step="any" min="0" :label="__('production::production.daily_target')" :value="old('daily_target', $workOrder->daily_target)" />
            <x-common.input name="start_date" type="date" :label="__('production::production.start_date')" :value="old('start_date', $workOrder->start_date?->format('Y-m-d'))" />
            <x-common.input name="due_date" type="date" :label="__('production::production.due_date')" :value="old('due_date', $workOrder->due_date?->format('Y-m-d'))" />
        </div>
        <datalist id="production-lines">@foreach ($lines as $line)<option value="{{ $line }}">@endforeach</datalist>
        <x-common.textarea name="notes" :label="__('production::production.notes')" :value="old('notes', $workOrder->notes)" :rows="3" />
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
