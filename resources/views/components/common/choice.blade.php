@props([
    'name',
    'value',
    'label',
    'checked' => false,
    'type' => 'radio',
])

@php
    $id = $attributes->get('id') ?: 'choice-'.\Illuminate\Support\Str::slug($name.'-'.$value, '-');
@endphp

<label
    for="{{ $id }}"
    {{ $attributes->only('class')->merge(['class' => 'flex items-center justify-center min-h-12 px-4 rounded-xl border border-line text-sm font-semibold cursor-pointer transition-all duration-200 has-[:checked]:border-brand has-[:checked]:text-brand has-[:checked]:bg-brand-light has-[:checked]:shadow-sm hover:border-brand/60']) }}
>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ $value }}"
        class="sr-only"
        @checked($checked)
        {{ $attributes->except(['class', 'id']) }}
    >
    {{ $label }}
</label>
