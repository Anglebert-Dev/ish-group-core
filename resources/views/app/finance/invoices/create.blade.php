@php
    $invoice = $invoice ?? null;
    $cancelHref = route('finance.invoices.index');
    $customerOptions = collect($customers ?? [])->mapWithKeys(fn ($c) => [$c->id => $c->name])->all();
    $corporateOptions = collect($corporates ?? [])->mapWithKeys(fn ($c) => [$c->id => $c->company_name])->all();
    $bookingOptions = collect($bookings ?? [])->mapWithKeys(fn ($b) => [$b->id => $b->booking_number])->all();
    $lines = old('lines', $invoice?->lines?->map(fn ($l) => [
        'description' => $l->description,
        'quantity' => $l->quantity,
        'unit_price' => $l->unit_price,
    ])->all() ?? [['description' => '', 'quantity' => 1, 'unit_price' => '']]);
@endphp

<x-layouts.app title="{{ $invoice ? __('body.edit_invoice') : __('body.add_invoice') }}">
    <x-common.page-header :title="$invoice ? __('body.edit_invoice') : __('body.add_invoice')" />

    <form method="POST" action="{{ $invoice ? route('finance.invoices.update', $invoice) : route('finance.invoices.store') }}" class="bg-white border border-line rounded-xl p-6 space-y-5">
        @csrf
        @if ($invoice) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.input type="date" name="invoice_date" :label="__('body.date')" :value="old('invoice_date', optional($invoice?->invoice_date)->format('Y-m-d') ?? now()->toDateString())" required />
            <x-common.select name="booking_id" :label="__('body.booking')" :value="old('booking_id', $invoice?->booking_id)" :options="$bookingOptions" />
            <x-common.select name="customer_id" :label="__('body.customer')" :value="old('customer_id', $invoice?->customer_id)" :options="$customerOptions" />
            <x-common.select name="corporate_customer_id" :label="__('body.corporate_customer')" :value="old('corporate_customer_id', $invoice?->corporate_customer_id)" :options="$corporateOptions" />
            <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $invoice?->notes)" :rows="2" class="sm:col-span-2" />
        </div>

        <div>
            <h3 class="font-semibold text-ink mb-3">{{ __('body.line_items') }}</h3>
            <div class="space-y-3" id="invoice-lines">
                @foreach ($lines as $i => $line)
                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                        <x-common.input name="lines[{{ $i }}][description]" :label="__('body.description')" :value="$line['description'] ?? ''" class="sm:col-span-3" required />
                        <x-common.input type="number" name="lines[{{ $i }}][quantity]" :label="__('body.quantity')" :value="$line['quantity'] ?? 1" min="0.01" step="0.01" required />
                        <x-common.input amount name="lines[{{ $i }}][unit_price]" :label="__('body.unit_price')" :value="$line['unit_price'] ?? ''" min="0" required class="sm:col-span-2" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <x-common.button href="{{ $cancelHref }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
            <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
