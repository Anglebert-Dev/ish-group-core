@php use Modules\Billing\Support\Money; @endphp
{{-- $action, $invoice, $title, $submitLabel --}}
<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="bg-white border border-line rounded-xl p-5 space-y-4">
    @csrf
    <div>
        <h2 class="font-display font-bold text-ink">{{ $title }}</h2>
        @isset($hint)<p class="text-sm text-muted mt-1">{{ $hint }}</p>@endisset
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-common.input name="amount" type="number" step="0.01" min="0.01" :label="__('body.amount').' ('.$invoice->currency.')'" :value="old('amount', Money::fromCents($invoice->balanceCents()))" required />
        <x-common.select name="method" :label="__('billing::billing.method')" :value="old('method', 'bank_transfer')" :options="\Modules\Billing\Enums\PaymentMethod::options()" hide-placeholder required />
        <x-common.input name="reference" :label="__('billing::billing.reference')" :value="old('reference')" :hint="__('billing::billing.reference_hint')" />
        <x-common.input name="paid_on" type="date" :label="__('billing::billing.paid_on')" :value="old('paid_on', now()->toDateString())" required />
    </div>
    <x-common.file-input name="proof" :label="__('billing::billing.proof')" accept=".jpg,.jpeg,.png,.webp,.pdf" :hint="__('billing::billing.proof_hint')" />
    <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes')" :rows="2" />
    <x-common.button type="submit">{{ $submitLabel }}</x-common.button>
</form>
