@props([
    'title',
    'icon' => null,
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'bg-brand-light text-brand',
        'ink' => 'bg-surface text-ink',
        'success' => 'bg-green-50 text-success',
        'warning' => 'bg-amber-50 text-warning',
        'danger' => 'bg-red-50 text-danger',
    ];
    $toneClass = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl sm:rounded-2xl shadow-sm border border-line p-4 sm:p-5 md:p-6 lg:p-8 min-w-0']) }}>
    <div class="flex items-center gap-3 mb-5 sm:mb-6 pb-4 border-b border-line">
        @if ($icon)
            <div class="p-2.5 rounded-xl {{ $toneClass }} shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
            </div>
        @endif
        <h2 class="text-lg sm:text-xl font-display font-bold text-ink">{{ $title }}</h2>
        @isset($actions)
            <div class="ml-auto flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</div>
