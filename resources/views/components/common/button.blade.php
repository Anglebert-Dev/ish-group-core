@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-colors whitespace-nowrap';

    $variants = [
        'primary'   => 'bg-brand text-white hover:bg-brand-dark',
        'secondary' => 'bg-white text-body border border-line hover:border-brand hover:text-brand',
        'ghost'     => 'text-body hover:text-brand',
        'danger'    => 'bg-danger text-white hover:opacity-90',
    ];

    $sizes = [
        'sm' => 'text-sm px-3.5 py-2',
        'md' => 'text-[15px] px-5 py-2.5',
        'lg' => 'text-base px-7 py-3.5',
    ];

    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>{{ $slot }}</button>
@endif
