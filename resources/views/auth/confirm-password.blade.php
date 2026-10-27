<x-layouts.auth
    :title="__('body.confirm_password_title')"
    :heading="__('body.confirm_password_title')"
    :subtitle="__('body.confirm_password_subtitle')"
    :panel-text="__('body.auth_panel_text_staff')"
>
    <form action="{{ route('staff.password.confirm.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-common.input type="password" name="password" :label="__('body.password')" required autofocus autocomplete="current-password" />

        <button type="submit" class="w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold text-[15px] transition-colors shadow-sm shadow-brand/25">
            {{ __('body.confirm_password_action') }}
        </button>
    </form>
</x-layouts.auth>
