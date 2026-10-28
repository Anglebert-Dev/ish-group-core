@php
    $isNew = ! $quote->exists;
    $rows = old('lines', $quote->lines->map(fn ($l) => [
        'variant' => $l->variant?->uuid, 'description' => $l->description,
        'quantity' => (float) $l->quantity, 'unit_price' => (float) $l->unit_price,
    ])->all());
@endphp
<x-layouts.app :title="$isNew ? __('quotations::quotations.add') : $quote->number">
    <x-common.page-header :title="$isNew ? ($quote->is_sample ? __('quotations::quotations.add_sample') : __('quotations::quotations.add')) : __('quotations::quotations.edit')" :description="$isNew ? null : $quote->number">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('quotations.quotes.index') : route('quotations.quotes.show', $quote) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('quotations.quotes.store') : route('quotations.quotes.update', $quote) }}" method="POST" class="space-y-6 max-w-6xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <x-common.select name="customer" :label="__('quotations::quotations.customer')" :value="old('customer', $quote->customer?->uuid)" :options="$customers" searchable required />
                <x-common.input name="quote_date" type="date" :label="__('quotations::quotations.quote_date')" :value="old('quote_date', $quote->quote_date?->format('Y-m-d'))" required />
                <x-common.input name="valid_until" type="date" :label="__('quotations::quotations.valid_until')" :value="old('valid_until', $quote->valid_until?->format('Y-m-d'))" />
                <x-common.input name="follow_up_date" type="date" :label="__('quotations::quotations.follow_up_date')" :value="old('follow_up_date', $quote->follow_up_date?->format('Y-m-d'))" />
                <x-common.select name="store" :label="__('quotations::quotations.store')" :value="old('store', $quote->store?->uuid ?? $defaultStore)" :options="$stores" hide-placeholder required />
                <x-common.input name="discount" type="number" step="any" min="0" :label="__('inventory::inventory.discount')" :value="old('discount', (float) $quote->discount ?: '')" />
            </div>
            @if ($isNew)
                <input type="hidden" name="is_sample" value="0">
                <x-common.checkbox name="is_sample" value="1" :label="__('quotations::quotations.is_sample')" :description="__('quotations::quotations.is_sample_hint')" :checked="(bool) old('is_sample', $quote->is_sample)" />
            @endif
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <h2 class="font-display font-bold text-ink">{{ __('quotations::quotations.lines') }}</h2>
            @include('inventory::partials.line-editor', ['lines' => $rows, 'options' => $lineOptions, 'name' => 'lines'])
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <x-common.textarea name="notes" :label="__('quotations::quotations.notes')" :value="old('notes', $quote->notes)" :rows="3" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
