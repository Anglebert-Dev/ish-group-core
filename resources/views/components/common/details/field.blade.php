@props([
    'label',
    'span' => null,
])

@php
    $spanClass = match ((int) $span) {
        2 => 'md:col-span-2',
        3 => 'md:col-span-2 lg:col-span-3',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => trim("p-4 bg-surface rounded-lg border border-transparent min-w-0 {$spanClass}")]) }}>
    <span class="text-muted font-medium block mb-1.5 text-sm">{{ $label }}</span>
    <div class="text-ink font-semibold break-words">{{ $slot->isEmpty() ? '—' : $slot }}</div>
</div>
