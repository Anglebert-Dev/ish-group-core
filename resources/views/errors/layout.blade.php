{{--
    Error pages (docs/branding.md §3.17): the business's look when it can be
    read; the built-in look when it can't (500 with the database down, 503).
    Plain CSS on purpose: no external scripts, so it shows in any state.
--}}
@php
    $css = null;
    $fontFaces = '';
    $name = config('app.name');
    $logo = null;
    try {
        if (isset($branding) && $branding?->exists) {
            $service = app(\App\Services\Branding\BrandingService::class);
            $css = $service->themeCss($branding);
            $font = $service->font($branding);
            $fontFaces = \App\Support\Branding\Fonts::faceCss($font).':root{--font-body:'.\App\Support\Branding\Fonts::stack($font).'}';
            $name = $branding->companyName();
            $logo = $branding->logoUrl();
        }
    } catch (\Throwable) {
        $css = null;
    }
    $css ??= \App\Support\Branding\ThemeTokens::css(\App\Support\Branding\ThemeTokens::resolve(\App\Models\Business\Business::DEFAULT_BRAND_COLOR));
    $home = \Illuminate\Support\Facades\Route::has('dashboard') && auth('staff')->check() ? route('dashboard') : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} · {{ $title }} — {{ $name }}</title>
    <style>
        {!! $fontFaces !!}
        {!! $css !!}
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
               background: rgb(var(--c-page)); color: rgb(var(--c-body)); font-family: var(--font-body, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif); }
        main { max-width: 26rem; width: 100%; text-align: center; }
        .logo { max-height: 44px; max-width: 180px; margin: 0 auto 28px; display: block; }
        .mark { width: 64px; height: 64px; border-radius: 9999px; margin: 0 auto 22px; display: flex; align-items: center; justify-content: center; background: rgb(var(--c-brand) / .1); color: rgb(var(--c-brand)); }
        .code { font-size: 48px; font-weight: 800; color: rgb(var(--c-ink)); margin: 0; letter-spacing: -.02em; }
        h1 { font-size: 20px; color: rgb(var(--c-ink)); margin: 10px 0 0; }
        p { color: rgb(var(--c-muted)); font-size: 14px; line-height: 1.6; margin: 8px 0 0; }
        a.button { display: inline-block; margin-top: 24px; padding: 10px 20px; border-radius: var(--radius-button, .5rem); background: rgb(var(--c-brand)); color: rgb(var(--c-on-brand, 255 255 255)); font-weight: 600; font-size: 15px; text-decoration: none; }
        a.button:hover { background: rgb(var(--c-brand-dark)); }
        .name { margin-top: 32px; font-size: 12px; color: rgb(var(--c-muted)); }
    </style>
</head>
<body>
<main>
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="{{ $name }}">
    @endif
    <div class="mark" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon ?? 'M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z' }}"/></svg>
    </div>
    <p class="code">{{ $code }}</p>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @unless ($hideButton ?? false)
        <a class="button" href="{{ $home }}">{{ __('body.error_home') }}</a>
    @endunless
    <p class="name">{{ $name }}</p>
</main>
</body>
</html>
