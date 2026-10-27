@props([
    'name',
    'label' => null,
    'value' => null,
    'required' => false,
    'hint' => null,
    'error' => null,
    'disabled' => false,
    'rows' => 4,
])

@php
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-');
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $errorMessage = $error ?? ($errors->first($errorKey) ?: null);
    $hasError = filled($errorMessage);
    $controlClass = 'box-border min-w-0 w-full resize-none rounded-xl border bg-white px-4 py-3 text-sm shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand placeholder:text-muted '
        .($hasError ? 'border-danger' : 'border-line').' '
        .($disabled ? 'opacity-50 cursor-not-allowed' : '');
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex min-w-0 flex-col gap-2 w-full']) }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-semibold text-ink">
            {{ $label }}
            @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-help" @endif
        {{ $attributes->except(['class', 'id'])->merge(['class' => $controlClass]) }}
    >{{ $value }}</textarea>

    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert">{{ $errorMessage }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-help" class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
