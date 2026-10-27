<x-layouts.auth
    :title="__('body.log_in')"
    :eyebrow="__('body.auth_staff_login')"
    :heading="__('body.auth_welcome_back')"
    :subtitle="__('body.auth_login_subtitle_staff')"
    :panel-text="__('body.auth_panel_text_staff')"
>
    <form action="{{ route('staff.login.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" :placeholder="__('body.placeholder_email')" required autofocus autocomplete="username" />
        <x-common.input type="password" name="password" :label="__('body.password')" :placeholder="__('body.placeholder_password')" required autocomplete="current-password" />

        <div class="flex flex-wrap items-center justify-between gap-3 text-sm pt-0.5">
            <x-common.checkbox
                id="remember"
                name="remember"
                :label="__('body.auth_remember_me')"
                :checked="(bool) old('remember')"
                inline
            />
            <a href="{{ route('staff.password.request') }}" class="font-semibold text-brand hover:text-brand-dark whitespace-nowrap">
                {{ __('body.auth_forgot_password') }}
            </a>
        </div>

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_sign_in') }}
        </button>
    </form>

    <x-slot:footer>
        <div class="space-y-2">
            @if ($registrationIsOpen)
                <p>
                    {{ $isFirstRegistration ? __('body.auth_setup_first_admin') : __('body.auth_register_business') }}
                    <a href="{{ route('staff.register') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_create_account') }}</a>
                </p>
            @endif
            @if ($hasPublicSite && Route::has('login'))
                <p>
                    {{ __('body.auth_customer_prompt') }}
                    <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_login_as_customer') }}</a>
                </p>
            @endif
        </div>
    </x-slot:footer>
</x-layouts.auth>
