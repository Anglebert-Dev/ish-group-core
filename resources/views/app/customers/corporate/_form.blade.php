@php
    $corporate = $corporate ?? null;
    $contacts = $corporate?->contacts?->keyBy('type') ?? collect();
    $cancelHref = $cancelHref ?? route('customers.corporate.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.company')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="company_name" :label="__('body.company_name')" :value="old('company_name', $corporate?->company_name)" required />
        <x-common.input name="tin" :label="__('body.tin')" :value="old('tin', $corporate?->tin)" />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $corporate?->email)" />
        <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $corporate?->phone)" />
        <x-common.input name="payment_terms" :label="__('body.payment_terms')" :value="old('payment_terms', $corporate?->payment_terms)" placeholder="e.g. Net 30" />
        <x-common.input amount name="credit_limit" :label="__('body.credit_limit')" :value="old('credit_limit', $corporate?->credit_limit)" min="0" />
        <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $corporate?->address)" :rows="2" class="sm:col-span-2" />
        <x-common.textarea name="billing_address" :label="__('body.billing_address')" :value="old('billing_address', $corporate?->billing_address)" :rows="2" class="sm:col-span-2" />
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $corporate?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.contacts')">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        @foreach (['primary' => __('body.primary_contact'), 'booking' => __('body.booking_contact'), 'finance' => __('body.finance_contact')] as $type => $label)
            <div class="border border-line rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-ink">{{ $label }}</p>
                <x-common.input name="contacts[{{ $type }}][name]" :label="__('body.contact_name')" :value="old('contacts.'.$type.'.name', $contacts[$type]->name ?? '')" />
                <x-common.input type="email" name="contacts[{{ $type }}][email]" :label="__('body.email_address')" :value="old('contacts.'.$type.'.email', $contacts[$type]->email ?? '')" />
                <x-common.phone-input name="contacts[{{ $type }}][phone]" :label="__('body.phone')" :value="old('contacts.'.$type.'.phone', $contacts[$type]->phone ?? '')" />
            </div>
        @endforeach
    </div>
    <x-common.wizard.nav />
</x-common.wizard.step>

<x-common.wizard.step :step="3" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.company_name')" for="company_name" />
        <x-common.wizard.review-row :label="__('body.tin')" for="tin" />
        <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
        <x-common.wizard.review-row :label="__('body.phone')" for="phone" />
        <x-common.wizard.review-row :label="__('body.payment_terms')" for="payment_terms" />
        <x-common.wizard.review-row :label="__('body.credit_limit')" for="credit_limit" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
