<x-layouts.auth
    :title="__('body.log_in')"
    :eyebrow="__('body.auth_customer_login')"
    :heading="__('body.auth_welcome_back')"
    :subtitle="__('body.auth_login_subtitle_customer')"
    :panel-text="__('body.auth_panel_text')"
>
    <form action="{{ route('login.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input name="login" :label="__('body.email_or_phone')" :value="old('login')" :placeholder="__('body.placeholder_email_or_phone')" required autofocus autocomplete="username" />
        <x-common.input type="password" name="password" :label="__('body.password')" :placeholder="__('body.placeholder_password')" required autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3 text-sm pt-0.5">
            <x-common.checkbox
                id="remember"
                name="remember"
                :label="__('body.auth_remember_me')"
                :checked="(bool) old('remember')"
                inline
            />
            <a href="{{ route('customer.password.request') }}" class="font-medium text-brand hover:text-brand-dark">{{ __('body.auth_forgot_password') }}</a>
        </div>

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_sign_in') }}
        </button>
    </form>

    <x-slot:footer>
        <div class="space-y-2">
            <p>
                {{ __('body.auth_no_account') }}
                <a href="{{ route('register') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.sign_up') }}</a>
            </p>
            <p>
                {{ __('body.auth_staff_member') }}
                <a href="{{ route('staff.login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_login_as_staff') }}</a>
            </p>
        </div>
    </x-slot:footer>
</x-layouts.auth>
