@props([
    'title',
    'level' => null,
    'summary' => null,
    'expiry' => null,
    'meta' => null,
    'url' => null,
    'showExpiry' => true,
])

@php
    /** @var \App\Enums\DocumentAlertLevel|null $level */
@endphp

<div {{ $attributes->class('rounded-xl border border-line p-4 bg-surface/40 space-y-2') }}>
    <div class="flex items-start justify-between gap-3">
        <p class="font-semibold text-ink">{{ $title }}</p>
        @if ($level)
            <x-common.badge :color="$level->tone()">{{ $level->label() }}</x-common.badge>
        @endif
    </div>

    @if ($showExpiry)
        <p class="text-sm text-body">
            {{ __('body.expires') }}:
            <span class="font-medium text-ink">{{ $expiry ? $expiry->format('d M Y') : '—' }}</span>
        </p>
        @if ($summary)
            <p class="text-xs text-muted">{{ $summary }}</p>
        @endif
    @elseif ($meta)
        <p class="text-sm text-body">{{ $meta }}</p>
    @endif

    @if ($meta && $showExpiry)
        <p class="text-xs text-muted">{{ $meta }}</p>
    @endif

    @if ($url)
        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex text-sm font-medium text-brand hover:text-brand-dark">
            {{ __('body.view_document') }}
        </a>
    @endif
</div>
