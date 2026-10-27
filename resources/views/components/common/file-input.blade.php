@props([
    'name',
    'label' => null,
    'accept' => 'image/*',
    'multiple' => false,
    'hint' => null,
    'existingUrl' => null,
    'existingUrls' => [],
    'maxSizeMb' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id') ?: 'file-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-').'-'.\Illuminate\Support\Str::random(5);
    $urls = collect($existingUrls)->filter()->values();
    if ($existingUrl) {
        $urls = $urls->prepend($existingUrl)->unique()->values();
    }
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $isImageUrl = static fn (string $url): bool => (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)(\?|$)/i', $url);
@endphp

<div
    class="file-input space-y-2"
    data-file-input
    data-multiple="{{ $multiple ? '1' : '0' }}"
    @if ($maxSizeMb) data-max-size-mb="{{ $maxSizeMb }}" @endif
>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-semibold text-ink">
            {{ $label }}
            @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    @if ($urls->isNotEmpty())
        <div class="flex flex-wrap gap-3">
            @foreach ($urls as $url)
                @if ($isImageUrl($url))
                    <img src="{{ $url }}" alt="" class="h-12 w-auto max-w-[8rem] object-contain rounded border border-line bg-surface p-1">
                @else
                    <a href="{{ $url }}" target="_blank" class="text-sm text-brand hover:text-brand-dark underline">{{ __('body.view_document') }}</a>
                @endif
            @endforeach
        </div>
        <p class="text-xs text-muted">{{ __('body.current_file_kept') }}</p>
    @endif

    <input
        type="file"
        name="{{ $name }}"
        id="{{ $id }}"
        accept="{{ $accept }}"
        @if ($multiple) multiple @endif
        @if ($required && $urls->isEmpty()) required @endif
        class="sr-only"
        data-file-field
        {{ $attributes->except(['id', 'class']) }}
    >

    <label
        for="{{ $id }}"
        data-dropzone
        class="relative block border-2 border-dashed border-line rounded-xl p-6 text-center transition-colors hover:border-brand hover:bg-brand-light/40 cursor-pointer"
    >
        <div class="space-y-2">
            <div class="mx-auto h-11 w-11 rounded-full bg-surface border border-line flex items-center justify-center text-muted">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-ink" data-dropzone-label>{{ __('body.click_to_upload') }}</p>
            <p class="text-xs text-muted">
                {{ $multiple ? __('body.multiple_files_allowed') : __('body.single_file_only') }}
                @if ($maxSizeMb)
                    · {{ __('body.max_file_size', ['size' => $maxSizeMb]) }}
                @endif
            </p>
        </div>
    </label>

    <div data-file-preview class="hidden flex flex-wrap gap-3"></div>

    @if ($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @error($errorKey)
        <p class="text-xs text-danger mt-1.5">{{ $message }}</p>
    @enderror
</div>
