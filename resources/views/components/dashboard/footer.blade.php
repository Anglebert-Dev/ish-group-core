{{-- The business's footer (Branding → Business details → Support & footer): only when something is set. --}}
@php
    $get = fn (string $key) => app(\App\Services\Branding\BrandingService::class)->detail($branding, $key);
    $text = $get('app_footer');
    $copyright = $get('copyright');
    $support = array_values(array_filter([$get('support_phone'), $get('support_email')]));
    $help = $get('help_url');
@endphp
@if ($text || $copyright || $support || $help)
    <footer class="app-footer mx-auto w-full max-w-[var(--content-max)] px-4 sm:px-5 lg:px-8 pb-6 text-xs text-muted flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="space-y-0.5">
            @if ($text)<p>{{ $text }}</p>@endif
            @if ($copyright)<p>{{ $copyright }}</p>@endif
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            @foreach ($support as $line)
                <span>{{ $line }}</span>
            @endforeach
            @if ($help)
                <a href="{{ $help }}" target="_blank" rel="noopener" class="font-semibold text-link hover:underline">{{ __('body.help_support') }}</a>
            @endif
        </div>
    </footer>
@endif
