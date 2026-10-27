<x-layouts.auth
    :title="__('body.auth_forgot_password')"
    :heading="__('body.auth_forgot_title')"
    :subtitle="__('body.auth_forgot_subtitle')"
    :panel-text="__('body.auth_panel_text_staff')"
>
    <form action="{{ route('staff.password.email') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input type="email" name="email" :label="__('body.email_address')" :value="old('email')" required autofocus autocomplete="username" />

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_send_reset_link') }}
        </button>
    </form>

    <x-slot:footer>
        <p>
            <a href="{{ route('staff.login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_back_to_login') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
