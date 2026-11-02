{{-- Light / dark / device, in the user menu: only when the business lets people choose (docs/branding.md §3.3). --}}
@php
    $authUser = auth('staff')->user();
    $themes = app(\App\Services\Branding\ThemeService::class);
    $allowed = $authUser && $themes->allowsUserMode($authUser->business);
    $currentAppearance = $authUser?->appearance ?? 'business';
@endphp

@if ($allowed)
    <div class="px-4 pt-2 pb-1 border-t border-line">
        <p class="text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('body.appearance') }}</p>
    </div>
    @foreach (['business', 'light', 'dark', 'system'] as $choice)
        <form action="{{ route('staff.appearance') }}" method="POST">
            @csrf
            <input type="hidden" name="appearance" value="{{ $choice }}">
            <button type="submit" class="flex items-center justify-between gap-3 px-4 py-2 text-sm text-ink hover:bg-surface transition-colors w-full text-left" @if ($choice === $currentAppearance) aria-current="true" @endif>
                <span>{{ __('body.appearance_'.$choice) }}</span>
                @if ($choice === $currentAppearance)
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                @endif
            </button>
        </form>
    @endforeach
@endif
