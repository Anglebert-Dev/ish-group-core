{{-- Shown to signed-in staff before a sensitive action: stays inside the app. --}}
<x-layouts.app :title="__('body.confirm_password_title')">
    <div class="min-h-[60vh] flex items-center justify-center">
        <div class="w-full max-w-md bg-white border border-line rounded-2xl p-6 sm:p-8 shadow-sm">
            <div class="h-12 w-12 rounded-xl bg-brand-light text-brand flex items-center justify-center mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>

            <h1 class="font-display font-bold text-xl text-ink">{{ __('body.confirm_password_title') }}</h1>
            <p class="text-sm text-muted mt-1.5 mb-6">{{ __('body.confirm_password_subtitle') }}</p>

            <form action="{{ route('staff.password.confirm.store') }}" method="POST" class="space-y-4">
                @csrf
                <x-common.input type="password" name="password" :label="__('body.password')" required autofocus autocomplete="current-password" />

                <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                    <x-common.button href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" variant="secondary">{{ __('body.cancel') }}</x-common.button>
                    <x-common.button type="submit">{{ __('body.confirm_password_action') }}</x-common.button>
                </div>
            </form>

            <p class="text-xs text-muted mt-6">{{ __('body.confirm_password_note', ['hours' => max(1, round(config('auth.password_timeout', 10800) / 3600))]) }}</p>
        </div>
    </div>
</x-layouts.app>
