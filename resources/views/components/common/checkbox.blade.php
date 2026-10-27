@props([
    'name',
    'label' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
    'description' => null,
    'inline' => false,
])

@php
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-').'-'.\Illuminate\Support\Str::slug((string) $value, '-');
    $labelClass = $inline
        ? 'inline-flex items-center gap-2 text-sm text-body cursor-pointer select-none'
        : 'flex items-start gap-3 p-4 rounded-xl bg-surface border border-line cursor-pointer transition-colors hover:border-brand';
    $boxClass = $inline
        ? 'pointer-events-none flex h-4 w-4 items-center justify-center rounded border border-line bg-white text-transparent peer-checked:bg-brand peer-checked:border-brand peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand/30 transition-colors'
        : 'pointer-events-none flex h-5 w-5 items-center justify-center rounded-md border border-line bg-white text-transparent peer-checked:bg-brand peer-checked:border-brand peer-checked:text-white transition-colors';
    $inputClass = $inline
        ? 'peer absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0'
        : 'peer sr-only';
@endphp

<label
    for="{{ $id }}"
    {{ $attributes->only('class')->merge(['class' => $labelClass.($disabled ? ' opacity-50 cursor-not-allowed' : '')]) }}
>
    <span class="relative {{ $inline ? 'inline-flex h-4 w-4' : 'mt-0.5' }} shrink-0">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ $value }}"
            @checked($checked)
            @if ($disabled) disabled @endif
            class="{{ $inputClass }}"
            {{ $attributes->except(['class', 'id']) }}
        >
        <span class="{{ $boxClass }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="{{ $inline ? 'h-2.5 w-2.5' : 'h-3 w-3' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </span>
    </span>
    <span class="min-w-0">
        @if ($label)
            <span class="{{ $inline ? '' : 'block text-sm font-semibold text-ink' }}">{{ $label }}</span>
        @endif
        @if ($description)
            <span class="block text-xs text-muted mt-0.5">{{ $description }}</span>
        @endif
        {{ $slot }}
    </span>
</label>
