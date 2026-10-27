<x-layouts.auth
    :title="__('body.mfa_verify_title')"
    :heading="__('body.mfa_verify_title')"
    :subtitle="__('body.mfa_required')"
    :panel-text="__('body.auth_panel_text_staff')"
>
    <form action="{{ route('staff.mfa.verify') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input name="code" :label="__('body.mfa_code')" :value="old('code')" required autofocus autocomplete="one-time-code" inputmode="numeric" />
        <p class="text-xs text-muted -mt-2">{{ __('body.mfa_recovery_code_hint') }}</p>

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.auth_sign_in') }}
        </button>
    </form>

    <x-slot:footer>
        <p>
            <a href="{{ route('staff.login') }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('body.auth_back_to_login') }}</a>
        </p>
    </x-slot:footer>
</x-layouts.auth>
