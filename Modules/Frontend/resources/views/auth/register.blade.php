<x-layouts.auth
    :title="__('body.auth_create_account')"
    :heading="__('body.auth_register_title_customer')"
    :subtitle="__('body.auth_register_subtitle_customer')"
    :panel-text="__('body.auth_panel_text')"
>
    <form action="{{ route('register.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input name="name" :label="__('body.full_name')" :value="old('name')" :placeholder="__('body.placeholder_full_name')" required autofocus autocomplete="name" />
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" :placeholder="__('body.placeholder_email')" required autocomplete="username" />
        <x-common.phone-input name="phone" :label="__('body.phone_number')" :value="old('phone')" :placeholder="__('body.phone_national_placeholder')" />
        <x-common.input type="password" name="password" :label="__('body.password')" :placeholder="__('body.placeholder_password_create')" required autocomplete="new-password" strength />
        <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" :placeholder="__('body.placeholder_password_confirm')" required autocomplete="new-password" confirm-for="field-password" />

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_create_account') }}
        </button>
    </form>

    <x-slot:footer>
        <p>
            {{ __('body.auth_have_account') }}
            <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_sign_in') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
