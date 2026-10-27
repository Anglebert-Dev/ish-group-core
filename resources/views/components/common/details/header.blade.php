@props([
    'title',
    'subtitle' => null,
    'backHref' => null,
    'backLabel' => null,
])

@php
    $backLabel = $backLabel ?? __('body.back');
@endphp

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-line p-4 sm:p-5 md:p-6 lg:p-8 min-w-0">
        @if ($backHref)
            <a href="{{ $backHref }}" class="inline-flex items-center gap-2 text-sm text-muted hover:text-ink mb-4 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span>{{ $backLabel }}</span>
            </a>
        @endif

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5 lg:gap-6">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    @isset($icon)
                        <div class="shrink-0">{{ $icon }}</div>
                    @endisset
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-display font-bold text-ink break-words">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="text-sm text-muted mt-1">{{ $subtitle }}</p>
                        @endif
                        @isset($meta)
                            <div class="flex flex-wrap items-center gap-2 mt-2">{{ $meta }}</div>
                        @endisset
                    </div>
                </div>
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-stretch sm:items-center gap-2 sm:gap-3 w-full lg:w-auto lg:justify-end [&>*]:flex-1 sm:[&>*]:flex-none">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>
</div>
