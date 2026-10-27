@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'hint' => null,
    'error' => null,
    'min' => null,
    'compact' => false,
    'pair' => null,
    'pairGroup' => null,
    'datePlaceholder' => null,
    'timePlaceholder' => null,
    'heading' => null,
])

@php
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-');
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $errorMessage = $error ?? (isset($errors) ? ($errors->first($errorKey) ?: null) : null);
    $hasError = filled($errorMessage);
    $raw = (string) ($value ?? '');
    $dateValue = '';
    $timeValue = '';

    if ($raw !== '') {
        if (str_contains($raw, 'T')) {
            [$dateValue, $timePart] = explode('T', $raw, 2);
            $timeValue = substr($timePart, 0, 5);
        } elseif (strlen($raw) >= 10) {
            $dateValue = substr($raw, 0, 10);
        }
    }

    $minDate = null;
    $minTime = null;
    if (filled($min)) {
        $minStr = (string) $min;
        $minDate = str_contains($minStr, 'T') ? substr($minStr, 0, 10) : substr($minStr, 0, 10);
        $minTime = str_contains($minStr, 'T') ? substr($minStr, 11, 5) : null;
    }

    $datePlaceholder = $datePlaceholder ?? __('body.hero_pick_up_date');
    $timePlaceholder = $timePlaceholder ?? __('body.hero_time');
    $useCompact = (bool) $compact;
    $labelClass = $useCompact
        ? 'text-[11px] font-semibold uppercase tracking-wide text-muted'
        : 'text-sm font-semibold text-ink';
@endphp

<div
    {{ $attributes->only('class')->merge(['class' => 'dt-split flex min-w-0 flex-col '.($useCompact ? 'gap-1.5' : 'gap-2').' w-full']) }}
    data-datetime-split
    @if ($pair) data-datetime-pair="{{ $pair }}" @endif
    @if ($pairGroup) data-datetime-pair-group="{{ $pairGroup }}" @endif
    @if (filled($min)) data-datetime-min="{{ $min }}" @endif
>
    @if ($heading)
        <p class="dt-split-heading">{{ $heading }}</p>
    @elseif ($label)
        <label class="{{ $labelClass }}" id="{{ $id }}-label">
            {{ $label }}
            @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="dt-split-row {{ $useCompact ? 'dt-split-row--compact' : '' }}">
        <div class="dt-split-box dt-split-date" data-open-part="date">
            <svg xmlns="http://www.w3.org/2000/svg" class="dt-split-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="dt-split-text {{ $dateValue !== '' ? 'is-filled' : '' }}" data-placeholder="{{ $datePlaceholder }}">
                {{ $dateValue !== '' ? $dateValue : $datePlaceholder }}
            </span>
            <input
                type="date"
                class="dt-split-native"
                data-part="date"
                value="{{ $dateValue }}"
                @if ($minDate) min="{{ $minDate }}" @endif
                @if ($required) required @endif
                tabindex="-1"
                aria-label="{{ $datePlaceholder }}"
            >
        </div>
        <div class="dt-split-box dt-split-time" data-open-part="time">
            <span class="dt-split-text {{ $timeValue !== '' ? 'is-filled' : '' }}" data-placeholder="{{ $timePlaceholder }}">
                {{ $timeValue !== '' ? $timeValue : $timePlaceholder }}
            </span>
            <input
                type="time"
                class="dt-split-native"
                data-part="time"
                value="{{ $timeValue }}"
                @if ($minDate && $minTime && $dateValue === $minDate) min="{{ $minTime }}" @endif
                @if ($required) required @endif
                tabindex="-1"
                aria-label="{{ $timePlaceholder }}"
            >
        </div>
    </div>

    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ $raw }}"
        data-datetime-combined
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except(['class', 'id']) }}
    >

    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert">{{ $errorMessage }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-help" class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
