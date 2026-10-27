<x-layouts.app :title="__('body.platform_settings')">
    <x-common.page-header :title="__('body.platform_settings')" :description="__('body.platform_settings_description')" />

    <form action="{{ route('superadmin.settings.update') }}" method="POST" class="max-w-2xl space-y-6">
        @csrf @method('PUT')

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
            <x-common.checkbox name="allow_registration" :label="__('body.allow_registration')"
                :description="__('body.allow_registration_hint')" :checked="$settings['allow_registration'] === '1'" />
            <x-common.checkbox name="default_business_active_status" :label="__('body.default_business_active_status')"
                :description="__('body.default_business_active_status_hint')" :checked="$settings['default_business_active_status'] === '1'" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
