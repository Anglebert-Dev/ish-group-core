@php $isNew = ! $order->exists; @endphp
<x-layouts.app :title="$isNew ? __('purchasing::purchasing.order_add') : $order->number">
    <x-common.page-header :title="$isNew ? __('purchasing::purchasing.order_add') : __('purchasing::purchasing.order_edit')" :description="$isNew ? null : $order->number">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('purchasing.orders.index') : route('purchasing.orders.show', $order) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('purchasing.orders.store') : route('purchasing.orders.update', $order) }}" method="POST" class="space-y-6 max-w-6xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div class="space-y-1">
                    <x-common.select name="supplier" :label="__('purchasing::purchasing.supplier')" :value="old('supplier', $order->supplier?->uuid)" :options="$suppliers" searchable required />
                    @if (\App\Helpers\AuthHelper::can('create purchasing.supplier'))
                        <a href="{{ route('purchasing.suppliers.create') }}" class="text-xs font-semibold text-brand">+ {{ __('purchasing::purchasing.supplier_add') }}</a>
                    @endif
                </div>
                <x-common.select name="store" :label="__('purchasing::purchasing.store')" :value="old('store', $order->store?->uuid ?? $defaultStore)" :options="$stores" hide-placeholder required />
                <x-common.input name="order_date" type="date" :label="__('purchasing::purchasing.order_date')" :value="old('order_date', $order->order_date?->format('Y-m-d'))" required />
                <x-common.input name="expected_date" type="date" :label="__('purchasing::purchasing.expected_date')" :value="old('expected_date', $order->expected_date?->format('Y-m-d'))" />
            </div>
        </div>
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('purchasing::purchasing.lines') }}</h2>
                <p class="text-sm text-muted">{{ __('purchasing::purchasing.lines_hint') }}</p>
            </div>
            @include('inventory::partials.line-editor', ['lines' => old('lines', $rows), 'options' => $lineOptions, 'name' => 'lines'])
        </div>
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <x-common.textarea name="notes" :label="__('purchasing::purchasing.notes')" :value="old('notes', $order->notes)" :rows="3" />
        </div>
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
