@props([
    'compact' => false,
])

@php
    $timezone = config('app.timezone');
    $now = now()->timezone($timezone);
@endphp

@if ($compact)
    <p {{ $attributes->merge(['class' => 'text-[11px] leading-snug text-muted']) }}>
        {{ __('body.timezone_note_short', ['timezone' => $timezone, 'abbr' => $now->format('T')]) }}
    </p>
@else
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-sm text-body']) }}>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-0.5 shrink-0 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="min-w-0">
            <p class="font-semibold text-ink">{{ __('body.timezone_title') }}</p>
            <p class="text-xs text-muted mt-0.5">
                {{ __('body.timezone_note', ['timezone' => $timezone, 'abbr' => $now->format('T'), 'time' => $now->format('H:i')]) }}
            </p>
        </div>
    </div>
@endif
