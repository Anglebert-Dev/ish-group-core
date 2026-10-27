@php
    $supplier = $supplier ?? null;
    $cancelHref = $cancelHref ?? route('finance.suppliers.index');
@endphp

<x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-common.input name="name" :label="__('body.supplier_name')" :value="old('name', $supplier?->name)" required class="sm:col-span-2" />
        <x-common.select name="type" :label="__('body.supplier_type')" :value="old('type', $supplier?->type?->value ?? \App\Enums\SupplierType::Services->value)" :options="\App\Enums\SupplierType::cases()" :hide-placeholder="true" required />
        <x-common.select name="status" :label="__('body.status')" :value="old('status', $supplier?->status?->value ?? \App\Enums\SupplierStatus::Active->value)" :options="\App\Enums\SupplierStatus::cases()" :hide-placeholder="true" />
        <x-common.input name="contact_name" :label="__('body.contact_name')" :value="old('contact_name', $supplier?->contact_name)" />
        <x-common.phone-input name="phone" :label="__('body.phone')" :value="old('phone', $supplier?->phone)" />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $supplier?->email)" />
        <x-common.input name="tin" :label="__('body.tin')" :value="old('tin', $supplier?->tin)" />
        <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $supplier?->address)" :rows="2" class="sm:col-span-2" />
        <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $supplier?->notes)" :rows="3" class="sm:col-span-2" />
    </div>
    <x-common.wizard.nav :show-back="false" :cancel-href="$cancelHref" />
</x-common.wizard.step>

<x-common.wizard.step :step="2" :title="__('body.review')">
    <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
    <dl>
        <x-common.wizard.review-row :label="__('body.supplier_name')" for="name" />
        <x-common.wizard.review-row :label="__('body.supplier_type')" for="type" />
        <x-common.wizard.review-row :label="__('body.status')" for="status" />
        <x-common.wizard.review-row :label="__('body.contact_name')" for="contact_name" />
        <x-common.wizard.review-row :label="__('body.phone')" for="phone" />
        <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
        <x-common.wizard.review-row :label="__('body.tin')" for="tin" />
        <x-common.wizard.review-row :label="__('body.address')" for="address" />
        <x-common.wizard.review-row :label="__('body.notes')" for="notes" />
    </dl>
    <x-common.wizard.nav :show-next="false" :show-submit="true" :cancel-href="$cancelHref" />
</x-common.wizard.step>
