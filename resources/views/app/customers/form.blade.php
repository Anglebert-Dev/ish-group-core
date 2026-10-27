@php $isNew = ! $customer->exists; @endphp
<x-layouts.app :title="$isNew ? __('body.customer_add') : $customer->displayName()">
    <x-common.page-header :title="$isNew ? __('body.customer_add') : __('body.customer_edit')" :description="$isNew ? null : $customer->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('customers.index') : route('customers.show', $customer) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('customers.store') : route('customers.update', $customer) }}" method="POST" class="space-y-6 max-w-4xl">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('body.customer_identity') }}</h2>
            <x-common.select name="type" :label="__('body.type')" :value="old('type', $customer->type?->value ?? 'company')" hide-placeholder required
                :options="['company' => __('body.customer_type_company'), 'person' => __('body.customer_type_person')]" />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="company_name" :label="__('body.customer_company_name')" :value="old('company_name', $customer->company_name)" :hint="__('body.customer_company_name_hint')" />
                <x-common.input name="name" :label="__('body.customer_name')" :value="old('name', $customer->name)" :hint="__('body.customer_name_hint')" required />
                <x-common.input name="contact_person" :label="__('body.customer_contact_person')" :value="old('contact_person', $customer->contact_person)" />
                <x-common.input name="tax_id" :label="__('body.customer_tax_id')" :value="old('tax_id', $customer->tax_id)" />
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('body.customer_contact') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="phone" :label="__('body.phone')" :value="old('phone', $customer->phone)" />
                <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $customer->email)" :hint="__('body.customer_email_hint')" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.textarea name="address" :label="__('body.address')" :value="old('address', $customer->address)" :rows="2" />
                <x-common.textarea name="delivery_address" :label="__('body.customer_delivery_address')" :value="old('delivery_address', $customer->delivery_address)" :rows="2" :hint="__('body.customer_delivery_hint')" />
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('body.customer_terms') }}</h2>
            <x-common.input name="payment_terms_days" type="number" min="0" :label="__('body.customer_payment_terms')" :value="old('payment_terms_days', $customer->payment_terms_days)" :hint="__('body.customer_payment_terms_hint')" class="max-w-xs" />
            <x-common.textarea name="notes" :label="__('body.notes')" :value="old('notes', $customer->notes)" :rows="4" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
