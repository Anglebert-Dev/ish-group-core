@props([
    'dark' => false,
    'stacked' => false,
    'compact' => false,
    'href' => null,
])

@php
    $custom = $branding->logoUrl();

    if ($custom) {
        $src = $custom;
    } else {
        $file = $stacked
            ? ($dark ? 'logo-stacked-white.png' : 'logo-stacked.png')
            : ($dark ? 'logo-horizontal-white.png' : 'logo-horizontal.png');
        $path = public_path('assets/images/logo/'.$file);
        $src = asset('assets/images/logo/'.$file).(file_exists($path) ? '?v='.filemtime($path) : '');
    }

    $imgClass = $stacked
        ? 'h-28 sm:h-32 w-auto max-w-full object-contain'
        : ($compact ? 'h-9 w-auto max-w-full object-contain object-left' : 'h-10 lg:h-12 w-auto max-w-full object-contain object-left');

    $link = $href ?? route('home');
@endphp

<a href="{{ $link }}" {{ $attributes->class('flex items-center shrink-0 min-w-0') }}>
    <img
        src="{{ $src }}"
        alt="{{ $branding->companyName() }}"
        class="{{ $imgClass }}"
    >
</a>
