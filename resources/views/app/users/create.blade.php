@php
    $steps = [
        ['id' => 'account', 'label' => __('body.account')],
        ['id' => 'role', 'label' => __('body.role')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_staff') }}">
    <x-common.page-header :title="__('body.add_staff')" />

    <x-common.wizard :action="route('users.store')" :steps="$steps">
        <x-common.wizard.step :step="1" :title="__('body.account')" :active="true">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="name" :label="__('body.full_name')" :value="old('name')" required autofocus />
                <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" required />
                <x-common.input type="password" name="password" :label="__('body.password')" required strength />
                <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required confirm-for="field-password" />
            </div>
            <x-common.wizard.nav :show-back="false" :cancel-href="route('users.index')" />
        </x-common.wizard.step>

        <x-common.wizard.step :step="2" :title="__('body.role')">
            <x-common.select name="role" :label="__('body.role')" :value="old('role')" :options="$roles->pluck('display_name', 'id')" :placeholder="'—'" />
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="3" :title="__('body.review')">
            <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
            <dl>
                <x-common.wizard.review-row :label="__('body.full_name')" for="name" />
                <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
                <x-common.wizard.review-row :label="__('body.role')" for="role" />
            </dl>
            <x-common.wizard.nav :show-next="false" :show-submit="true" :submit-label="__('body.send_invite')" :cancel-href="route('users.index')" />
        </x-common.wizard.step>
    </x-common.wizard>
</x-layouts.app>
