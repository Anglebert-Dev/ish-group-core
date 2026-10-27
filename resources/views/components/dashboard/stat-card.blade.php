@props([
    'label',
    'value',
    'change' => null,
    'trend' => 'up',
    'icon' => null,
    'href' => null,
    'hint' => null,
    'description' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $classes = 'relative group h-full min-h-[7.5rem] overflow-hidden bg-white border border-line rounded-xl p-4 sm:p-5 transition-all duration-200';
    if ($href) {
        $classes .= ' hover:-translate-y-0.5 hover:shadow-md hover:border-brand/30';
    }
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => $classes]) }} @if ($description) title="{{ $description }}" @endif>
    <div class="relative flex items-center justify-between gap-3 h-full">
        <div class="min-w-0 flex-1">
            <p class="text-xs sm:text-sm text-muted leading-tight">{{ $label }}</p>
            <p class="font-display font-bold text-xl sm:text-2xl text-ink mt-1.5 tabular-nums break-words">{{ $value }}</p>
            @if ($hint)
                <p class="text-xs font-medium text-brand mt-1">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <div class="h-10 w-10 shrink-0 rounded-lg bg-brand-light flex items-center justify-center text-brand transition-transform duration-200 group-hover:scale-105">
                {!! $icon !!}
            </div>
        @endif
    </div>

    @if ($change)
        <p class="text-xs mt-3 flex items-center gap-1 {{ $trend === 'up' ? 'text-success' : 'text-danger' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                @if ($trend === 'up')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                @endif
            </svg>
            {{ $change }}
            <span class="text-muted font-normal">{{ __('body.vs_last_month') }}</span>
        </p>
    @endif

    @if ($href)
        <span class="absolute bottom-0 left-0 right-0 h-0.5 bg-brand origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-200"></span>
    @endif
</{{ $tag }}>
