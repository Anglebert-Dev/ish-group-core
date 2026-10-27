<x-layouts.app :title="__('invoicing::invoicing.add')">
    <x-common.page-header :title="__('invoicing::invoicing.add')" :description="$salesOrder ? __('invoicing::invoicing.from_order', ['number' => $salesOrder->number]) : null">
        <x-slot:actions>
            <x-common.button href="{{ $salesOrder ? route('salesorders.orders.show', $salesOrder) : route('invoicing.invoices.index') }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ route('invoicing.invoices.store') }}" method="POST" class="space-y-6 max-w-6xl">
        @csrf
        @if ($salesOrder)
            <input type="hidden" name="sales_order" value="{{ $salesOrder->uuid }}">
            <div class="rounded-xl border border-brand/30 bg-brand-light/50 p-4 text-sm text-ink">{{ __('invoicing::invoicing.from_order_hint') }}</div>
        @endif

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <x-common.select name="customer" :label="__('invoicing::invoicing.customer')" :value="old('customer', $selectedCustomer)" :options="$customers" searchable required />
                <x-common.input name="invoice_date" type="date" :label="__('invoicing::invoicing.invoice_date')" :value="old('invoice_date', $invoice->invoice_date?->format('Y-m-d'))" required />
                <x-common.input name="due_date" type="date" :label="__('invoicing::invoicing.due_date')" :value="old('due_date', $invoice->due_date?->format('Y-m-d'))" />
                <x-common.select name="store" :label="__('invoicing::invoicing.store')" :value="old('store', $selectedStore)" :options="$stores" hide-placeholder required />
                <x-common.input name="discount" type="number" step="any" min="0" :label="__('inventory::inventory.discount')" :value="old('discount', (float) $invoice->discount ?: '')" />
                <div class="text-sm text-muted self-end pb-3">{{ __('invoicing::invoicing.tax_named', ['rate' => rtrim(rtrim(number_format($taxRate, 3), '0'), '.')]) }}</div>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <h2 class="font-display font-bold text-ink">{{ __('invoicing::invoicing.lines') }}</h2>
            @include('inventory::partials.line-editor', ['lines' => old('lines', $rows), 'options' => $lineOptions, 'name' => 'lines'])
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6">
            <x-common.textarea name="notes" :label="__('invoicing::invoicing.notes')" :value="old('notes')" :rows="3" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
