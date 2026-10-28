@php $isNew = ! $supplier->exists; @endphp
<x-layouts.app :title="$isNew ? __('purchasing::purchasing.supplier_add') : $supplier->name">
    <x-common.page-header :title="$isNew ? __('purchasing::purchasing.supplier_add') : __('purchasing::purchasing.supplier_edit')" />

    <form action="{{ $isNew ? route('purchasing.suppliers.store') : route('purchasing.suppliers.update', $supplier) }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-3xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.input name="name" :label="__('body.name')" :value="old('name', $supplier->name)" required />
            <x-common.input name="contact_person" :label="__('purchasing::purchasing.contact_person')" :value="old('contact_person', $supplier->contact_person)" />
            <x-common.input name="phone" :label="__('body.phone')" :value="old('phone', $supplier->phone)" />
            <x-common.input name="email" type="email" :label="__('body.email_address')" :value="old('email', $supplier->email)" />
            <x-common.input name="tax_id" :label="__('purchasing::purchasing.tax_id')" :value="old('tax_id', $supplier->tax_id)" />
            <x-common.input name="payment_terms_days" type="number" min="0" :label="__('purchasing::purchasing.payment_terms')" :value="old('payment_terms_days', $supplier->payment_terms_days)" />
        </div>
        <x-common.textarea name="address" :label="__('purchasing::purchasing.address')" :value="old('address', $supplier->address)" :rows="2" />
        <x-common.textarea name="notes" :label="__('purchasing::purchasing.notes')" :value="old('notes', $supplier->notes)" :rows="2" />
        @unless ($isNew)
            <input type="hidden" name="is_active" value="0">
            <x-common.checkbox name="is_active" value="1" :label="__('purchasing::purchasing.active')" :checked="$supplier->is_active" />
        @endunless
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
