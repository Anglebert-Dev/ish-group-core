<x-layouts.super-admin :title="__('body.add_business')">
    <x-common.page-header :title="__('body.add_business')" :description="__('body.add_business_description')" />

    <form action="{{ route('superadmin.businesses.store') }}" method="POST" class="space-y-6 max-w-2xl">
        @csrf

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('body.business') }}</h2>
            <x-common.input name="business_name" :label="__('body.business_name')" :value="old('business_name')" required autofocus />
            <input type="hidden" name="is_active" value="0">
            <x-common.checkbox name="is_active" :label="__('body.business_active_now')" :checked="old('is_active', true)" />
        </div>

        <div class="bg-white border border-line rounded-xl p-4 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('body.owner') }}</h2>
            <x-common.input name="name" :label="__('body.full_name')" :value="old('name')" required autocomplete="off" />
            <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" required autocomplete="off" />
            <x-common.input type="password" name="password" :label="__('body.password')" required autocomplete="new-password" strength />
            <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" confirm-for="field-password" />
        </div>

        <div class="flex gap-3">
            <x-common.button type="submit">{{ __('body.create_business') }}</x-common.button>
            <x-common.button href="{{ route('superadmin.businesses.index') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
        </div>
    </form>
</x-layouts.super-admin>
