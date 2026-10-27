@props(['make'])

@php
    $logoUrl = \App\Libraries\BrandLibrary::logoUrl($make);
@endphp

<div class="flex items-center justify-center h-12 sm:h-14 px-3 sm:px-4 grayscale opacity-55 hover:grayscale-0 hover:opacity-100 transition-all duration-300">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt=""
            class="h-8 sm:h-9 w-auto max-w-[7.5rem] object-contain"
            loading="lazy"
            decoding="async"
        >
    @else
        <span class="font-display font-bold text-lg tracking-[0.18em] uppercase text-ink whitespace-nowrap">{{ $make }}</span>
    @endif
    <span class="sr-only">{{ $make }}</span>
</div>
