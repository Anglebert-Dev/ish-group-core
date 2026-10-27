@props([
    'dark' => false,
    'stacked' => false,
    'compact' => false,
    'href' => null,
])

@php
    // Uploaded business logo when there is one (Settings → Assets);
    // otherwise a text wordmark built from the business name.
    $custom = $branding->logoUrl();
    $name = $branding->companyName();
    $initial = mb_strtoupper(mb_substr($name, 0, 1));

    $imgClass = $stacked
        ? 'h-28 sm:h-32 w-auto max-w-full object-contain'
        : ($compact ? 'h-9 w-auto max-w-full object-contain object-left' : 'h-10 lg:h-12 w-auto max-w-full object-contain object-left');

    $link = $href ?? route('home');
@endphp

<a href="{{ $link }}" {{ $attributes->class('flex items-center shrink-0 min-w-0') }}>
    @if ($custom)
        <img src="{{ $custom }}" alt="{{ $name }}" class="{{ $imgClass }}">
    @else
        <span class="flex {{ $stacked ? 'flex-col text-center' : 'items-center' }} gap-2.5 min-w-0">
            <span class="{{ $stacked ? 'h-14 w-14 text-2xl mx-auto' : ($compact ? 'h-8 w-8 text-sm' : 'h-9 w-9 text-base') }} shrink-0 rounded-lg bg-brand text-white font-display font-bold flex items-center justify-center">{{ $initial }}</span>
            <span class="font-display font-bold truncate {{ $compact ? 'text-base' : 'text-lg' }} {{ $dark ? 'text-white' : 'text-ink' }}">{{ $name }}</span>
        </span>
    @endif
</a>
