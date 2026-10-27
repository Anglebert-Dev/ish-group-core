<x-layouts.auth
    :home-url="route('site.home')"
    :title="__('body.auth_reset_password')"
    :heading="__('body.auth_reset_title')"
    :subtitle="__('body.auth_reset_subtitle_customer')"
    :panel-text="__('body.auth_panel_text')"
>
    <form action="{{ route('customer.password.update') }}" method="POST" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email', $email)" required autofocus autocomplete="username" />
        <x-common.input type="password" name="password" :label="__('body.new_password')" required autocomplete="new-password" strength />
        <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" confirm-for="field-password" />

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_reset_password') }}
        </button>
    </form>

    <x-slot:footer>
        <p>
            <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_back_to_login') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
