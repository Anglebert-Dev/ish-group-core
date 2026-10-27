@props([
    'cols' => 2,
])

@php
    $colsClass = match ((int) $cols) {
        3 => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4',
        4 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4',
        default => 'grid grid-cols-1 md:grid-cols-2 gap-4',
    };
@endphp

<div {{ $attributes->merge(['class' => $colsClass]) }}>
    {{ $slot }}
</div>
