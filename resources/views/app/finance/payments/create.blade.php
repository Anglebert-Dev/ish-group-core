@php
    $bookingOptions = collect($bookings ?? [])->mapWithKeys(fn ($b) => [$b->id => $b->booking_number])->all();
    $invoiceOptions = collect($invoices ?? [])->mapWithKeys(fn ($i) => [$i->id => $i->invoice_number])->all();
    $expenseOptions = collect($expenses ?? [])->mapWithKeys(fn ($e) => [$e->id => $e->expense_number])->all();
    $supplierOptions = collect($suppliers ?? [])->mapWithKeys(fn ($s) => [$s->id => $s->name])->all();
@endphp

<x-layouts.app title="{{ __('body.add_payment') }}">
    <x-common.page-header :title="__('body.add_payment')" />

    <form method="POST" action="{{ route('finance.payments.store') }}" class="bg-white border border-line rounded-xl p-6 space-y-5">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.input type="date" name="payment_date" :label="__('body.date')" :value="old('payment_date', now()->toDateString())" required />
            <x-common.input amount name="amount" :label="__('body.amount')" :value="old('amount')" required min="0.01" />
            <x-common.select name="method" :label="__('body.method')" :value="old('method', \App\Enums\PaymentMethod::Cash->value)" :options="\App\Enums\PaymentMethod::cases()" :hide-placeholder="true" required />
            <x-common.select name="booking_id" :label="__('body.booking')" :value="old('booking_id')" :options="$bookingOptions" />
            <x-common.select name="invoice_id" :label="__('body.invoice')" :value="old('invoice_id')" :options="$invoiceOptions" />
            <x-common.select name="expense_id" :label="__('body.expense')" :value="old('expense_id')" :options="$expenseOptions" />
            <x-common.select name="supplier_id" :label="__('body.supplier')" :value="old('supplier_id')" :options="$supplierOptions" />
            <x-common.input name="description" :label="__('body.description')" :value="old('description')" class="sm:col-span-2" />
            <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes')" :rows="2" class="sm:col-span-2" />
        </div>
        <p class="text-sm text-muted">{{ __('body.payment_target_hint') }}</p>
        <div class="flex justify-end gap-3">
            <x-common.button href="{{ route('finance.payments.index') }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
            <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
        </div>
    </form>
</x-layouts.app>
