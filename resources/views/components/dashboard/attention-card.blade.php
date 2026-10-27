@props([
    'title',
    'href' => null,
    'linkLabel' => null,
    'tone' => 'info',
])

@php
    $tones = [
        'info' => [
            'card' => 'border-blue-100 bg-blue-50/60',
            'row' => 'border-blue-100/80 hover:bg-blue-100/50',
            'link' => 'text-blue-600 hover:underline',
            'icon' => 'text-blue-600',
            'date' => 'text-blue-600',
        ],
        'danger' => [
            'card' => 'border-red-100 bg-red-50/60',
            'row' => 'border-red-100/80 hover:bg-red-100/50',
            'link' => 'text-red-600 hover:underline',
            'icon' => 'text-red-600',
            'date' => 'text-red-600',
        ],
        'warning' => [
            'card' => 'border-amber-100 bg-amber-50/60',
            'row' => 'border-amber-100/80 hover:bg-amber-100/50',
            'link' => 'text-amber-700 hover:underline',
            'icon' => 'text-amber-600',
            'date' => 'text-amber-700',
        ],
    ];
    $palette = $tones[$tone] ?? $tones['info'];
@endphp

<section {{ $attributes->merge(['class' => 'rounded-xl border overflow-hidden '.$palette['card']]) }}>
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <h2 class="text-sm sm:text-base font-semibold text-ink flex items-center gap-2 min-w-0">
            @isset($icon)
                <span class="{{ $palette['icon'] }} shrink-0">{{ $icon }}</span>
            @endisset
            <span class="truncate">{{ $title }}</span>
        </h2>
        @if ($href)
            <a href="{{ $href }}" class="text-sm font-medium shrink-0 {{ $palette['link'] }}">{{ $linkLabel ?? __('body.view_all') }}</a>
        @endif
    </div>
    <div class="px-4 pb-3">
        {{ $slot }}
    </div>
</section>
