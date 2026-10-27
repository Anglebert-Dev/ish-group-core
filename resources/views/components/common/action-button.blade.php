@props([
    'href' => null,       // link
    'action' => null,     // or a form submission
    'method' => 'POST',
    'color' => 'brand',   // brand | success | warning | danger | muted
    'icon' => null,       // optional SVG path
])

@php
    $colors = [
        'brand' => 'border-brand/40 text-brand hover:bg-brand-light',
        'success' => 'border-green-300 text-green-700 hover:bg-green-50',
        'warning' => 'border-amber-300 text-amber-700 hover:bg-amber-50',
        'danger' => 'border-red-300 text-danger hover:bg-red-50',
        'muted' => 'border-line text-muted hover:bg-surface',
    ];
    $classes = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold transition-colors '.($colors[$color] ?? $colors['brand']);
    $spoof = strtoupper($method);
@endphp

{{-- Small outlined row action (Manage, Deactivate, Delete, Log in as, ...). --}}
@if ($action)
    <form action="{{ $action }}" method="POST" class="inline">
        @csrf
        @if ($spoof !== 'POST')
            @method($spoof)
        @endif
        {{ $fields ?? '' }}
        <button type="submit" {{ $attributes->merge(['class' => $classes]) }}>
            @if ($icon)<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>@endif
            {{ $slot }}
        </button>
    </form>
@else
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>@endif
        {{ $slot }}
    </a>
@endif
