@props([
    'label',
    'value' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 min-w-0 shrink-0']) }}>
    <span class="text-muted shrink-0">{{ $label }}</span>
    <span class="font-semibold text-ink min-w-0 truncate">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            {{ $value ?? '—' }}
        @endif
    </span>
</div>
