@php
    $steps = [
        ['id' => 'account', 'label' => __('body.account')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_customer') }}">
    <x-common.page-header :title="__('body.add_customer')" :description="__('body.add_customer_description')" />

    <x-common.wizard :action="route('customers.store')" :steps="$steps">
        <x-common.wizard.step :step="1" :title="__('body.account')" :active="true">
            <p class="text-sm text-muted">{{ __('body.add_customer_no_password_hint') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="name" :label="__('body.full_name')" :value="old('name')" required autofocus />
                <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" required />
                <x-common.phone-input name="phone" :label="__('body.phone_number')" :value="old('phone')" :placeholder="__('body.phone_national_placeholder')" required class="sm:col-span-2" />
                <x-common.input name="national_id" :label="__('body.national_id')" :value="old('national_id')" required />
                <x-common.input name="driver_license_number" :label="__('body.driver_license_id')" :value="old('driver_license_number')" required />
                <x-common.textarea name="address" :label="__('body.address')" :value="old('address')" :rows="2" required class="sm:col-span-2" />
            </div>
            <x-common.wizard.nav :show-back="false" :cancel-href="route('customers.index')" />
        </x-common.wizard.step>

        <x-common.wizard.step :step="2" :title="__('body.review')">
            <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
            <dl>
                <x-common.wizard.review-row :label="__('body.full_name')" for="name" />
                <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
                <x-common.wizard.review-row :label="__('body.phone_number')" for="phone" />
                <x-common.wizard.review-row :label="__('body.national_id')" for="national_id" />
                <x-common.wizard.review-row :label="__('body.driver_license_id')" for="driver_license_number" />
                <x-common.wizard.review-row :label="__('body.address')" for="address" />
            </dl>
            <x-common.wizard.nav :show-next="false" :show-submit="true" :submit-label="__('body.save_customer')" :cancel-href="route('customers.index')" />
        </x-common.wizard.step>
    </x-common.wizard>
</x-layouts.app>
