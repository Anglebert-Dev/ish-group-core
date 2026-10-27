@php
    $steps = [
        ['id' => 'details', 'label' => __('body.details')],
        ['id' => 'review', 'label' => __('body.review')],
    ];
    $isSuperAdmin = $user->hasRole('SuperAdmin');
    $roleHint = $isSuperAdmin ? "SuperAdmin's role cannot be changed." : null;
@endphp

<x-layouts.app title="{{ __('body.edit_staff') }}">
    <x-common.page-header :title="__('body.edit_staff')" :description="$user->name" />

    <x-common.wizard :action="route('users.update', $user)" method="PUT" :steps="$steps">
        <x-common.wizard.step :step="1" :title="__('body.details')" :active="true">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="name" :label="__('body.full_name')" :value="old('name', $user->name)" required autofocus />
                <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $user->email)" required />
                <x-common.select
                    name="role"
                    :label="__('body.role')"
                    :value="old('role', $currentRole)"
                    :options="$roles->pluck('name', 'name')"
                    :placeholder="'—'"
                    :disabled="$isSuperAdmin"
                    :hint="$roleHint"
                />
            </div>
            <x-common.wizard.nav :show-back="false" :cancel-href="route('users.index')" />
        </x-common.wizard.step>

        <x-common.wizard.step :step="2" :title="__('body.review')">
            <p class="text-sm text-muted">{{ __('body.confirm_details') }}</p>
            <dl>
                <x-common.wizard.review-row :label="__('body.full_name')" for="name" />
                <x-common.wizard.review-row :label="__('body.email_address')" for="email" />
                <x-common.wizard.review-row :label="__('body.role')" for="role" />
            </dl>
            <x-common.wizard.nav :show-next="false" :show-submit="true" :submit-label="__('body.save_changes')" :cancel-href="route('users.index')" />
        </x-common.wizard.step>
    </x-common.wizard>
</x-layouts.app>
