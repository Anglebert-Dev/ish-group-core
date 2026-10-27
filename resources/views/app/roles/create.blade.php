@php
    $steps = [
        ['id' => 'name', 'label' => __('body.details')],
        ['id' => 'permissions', 'label' => __('body.permissions_step')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
@endphp

<x-layouts.app title="{{ __('body.add_role') }}">
    <x-common.page-header :title="__('body.add_role')" />

    <x-common.wizard :action="route('roles.store')" :steps="$steps">
        <x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
            <x-common.input name="name" :label="__('body.role_name')" :value="old('name')" required autofocus class="max-w-sm" />
            <x-common.wizard.nav :show-back="false" :cancel-href="route('roles.index')" />
        </x-common.wizard.step>

        <x-common.wizard.step :step="2" :title="__('body.permissions')">
            @include('app.roles._permission-grid', ['catalogue' => $catalogue, 'assigned' => $assigned])
            <x-common.wizard.nav />
        </x-common.wizard.step>

        <x-common.wizard.step :step="3" :title="__('body.review')">
            <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
            <dl>
                <x-common.wizard.review-row :label="__('body.role_name')" for="name" />
            </dl>
            <x-common.wizard.nav :show-next="false" :show-submit="true" :submit-label="__('body.save_role')" :cancel-href="route('roles.index')" />
        </x-common.wizard.step>
    </x-common.wizard>
</x-layouts.app>
