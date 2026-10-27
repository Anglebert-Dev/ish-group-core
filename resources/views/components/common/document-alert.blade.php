@props([
    'level',
    'summary' => null,
])

@php
    /** @var \App\Enums\DocumentAlertLevel $level */
    $color = $level->tone();
@endphp

<span {{ $attributes->class('inline-flex flex-col gap-0.5 min-w-0') }}>
    <x-common.badge :color="$color">{{ $level->label() }}</x-common.badge>
    @if ($summary)
        <span class="text-xs text-muted">{{ $summary }}</span>
    @endif
</span>
