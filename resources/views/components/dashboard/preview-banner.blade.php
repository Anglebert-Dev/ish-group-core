{{-- Shown while this user previews their business's unpublished branding (docs/branding.md §3.15). --}}
@if (isset($branding) && app(\App\Services\Branding\BrandingService::class)->previewing($branding))
    <div class="sticky top-0 z-30 bg-ink text-white">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-4 sm:px-5 lg:px-8 py-2 text-sm">
            <p class="font-medium">{{ __('body.theme_previewing') }}</p>
            <div class="flex flex-wrap gap-2 shrink-0">
                @if (\App\Helpers\AuthHelper::can('approve settings.setting'))
                    <form action="{{ route('settings.theme.publish') }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-lg bg-brand hover:bg-brand-dark px-3 py-1.5 font-semibold transition-colors">{{ __('body.theme_publish') }}</button>
                    </form>
                @endif
                <form action="{{ route('settings.theme.preview.stop') }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-white/20 hover:bg-white/30 px-3 py-1.5 font-semibold transition-colors">{{ __('body.theme_stop_preview') }}</button>
                </form>
            </div>
        </div>
    </div>
@endif
