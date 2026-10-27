@php
    $isNew = ! $order->exists;
    $linesEditable = $isNew || $order->isEditable();
    $rows = old('lines', $order->lines->map(fn ($l) => [
        'variant' => $l->variant?->uuid, 'description' => $l->description,
        'quantity' => (float) $l->quantity, 'unit_price' => (float) $l->unit_price,
    ])->all());
@endphp
<x-layouts.app :title="$isNew ? __('salesorders::orders.add') : $order->number">
    <x-common.page-header :title="$isNew ? __('salesorders::orders.add') : __('salesorders::orders.edit')" :description="$isNew ? null : $order->number">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('salesorders.orders.index') : route('salesorders.orders.show', $order) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('salesorders.orders.store') : route('salesorders.orders.update', $order) }}" method="POST" class="space-y-6 max-w-6xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="space-y-1">
                    <x-common.select name="customer" :label="__('salesorders::orders.customer')" :value="old('customer', $order->customer?->uuid)" :options="$customers" searchable required />
                    @if (\App\Helpers\AuthHelper::can('create customers.customer'))
                        <a href="{{ route('customers.create') }}" class="text-xs font-semibold text-brand hover:text-brand-dark">+ {{ __('salesorders::orders.customer_add') }}</a>
                    @endif
                </div>
                <x-common.input name="order_date" type="date" :label="__('salesorders::orders.order_date')" :value="old('order_date', $order->order_date?->format('Y-m-d'))" required />
                <x-common.input name="due_date" type="date" :label="__('salesorders::orders.due_date')" :value="old('due_date', $order->due_date?->format('Y-m-d'))" />
                <x-common.select name="store" :label="__('salesorders::orders.store')" :value="old('store', $order->store?->uuid ?? $defaultStore)" :options="$stores" hide-placeholder required />
                <x-common.input name="customer_reference" :label="__('salesorders::orders.customer_reference')" :value="old('customer_reference', $order->customer_reference)" :hint="__('salesorders::orders.customer_reference_hint')" />
                <x-common.input name="discount" type="number" step="any" min="0" :label="__('inventory::inventory.discount')" :value="old('discount', (float) $order->discount ?: '')" />
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <h2 class="font-display font-bold text-ink">{{ __('salesorders::orders.lines') }}</h2>
            @if ($linesEditable)
                @include('inventory::partials.line-editor', ['lines' => $rows, 'options' => $lineOptions, 'name' => 'lines'])
            @else
                <p class="text-sm text-muted">{{ __('salesorders::orders.lines_locked_hint') }}</p>
                @include('salesorders::orders.lines-table', ['order' => $order])
            @endif
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <x-common.textarea name="notes" :label="__('salesorders::orders.notes')" :value="old('notes', $order->notes)" :rows="3" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
