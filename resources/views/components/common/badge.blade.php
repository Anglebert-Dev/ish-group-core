@props(['color' => 'brand'])

@php
    $colors = [
        'brand'   => 'bg-brand-light text-brand-dark',
        'success' => 'bg-green-50 text-success',
        'warning' => 'bg-amber-50 text-warning',
        'danger'  => 'bg-red-50 text-danger',
        'muted'   => 'bg-slate-100 text-muted',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold ' . ($colors[$color] ?? $colors['brand'])]) }}>
    {{ $slot }}
</span>
