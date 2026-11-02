@props(['color' => 'brand'])

@php
    $colors = [
        'brand'   => 'bg-brand-light text-brand-dark',
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-warning/10 text-warning',
        'danger'  => 'bg-danger/10 text-danger',
        'muted'   => 'bg-surface text-muted',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold ' . ($colors[$color] ?? $colors['brand'])]) }}>
    {{ $slot }}
</span>
