@props([
    'name',
    'label' => null,
    'value' => null,
    'options' => null,
    'required' => false,
    'hint' => null,
    'error' => null,
    'disabled' => false,
    'placeholder' => null,
    'hidePlaceholder' => false,
    'searchable' => null,
    'searchableThreshold' => 8,
    'compact' => false,
])

@php
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-');
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $errorMessage = $error ?? ($errors->first($errorKey) ?: null);
    $hasError = filled($errorMessage);
    $placeholderLabel = $placeholder ?? ($label ? __('body.select_option', ['label' => $label]) : '—');
    $isCompact = (bool) $compact;

    // Match NDFIS SelectField control styling (brand tokens instead of blue/gray).
    $controlClass = ($isCompact ? 'h-10 min-h-10 ' : 'h-12 min-h-12 ')
        .'w-full px-3 sm:px-4 rounded-xl border text-sm bg-white shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand cursor-pointer text-ink '
        .($hasError ? 'border-danger' : 'border-line').' '
        .($disabled ? 'opacity-70 cursor-not-allowed' : '');

    $normalized = [];
    if (is_iterable($options)) {
        $optionsArray = is_array($options) ? $options : collect($options)->all();
        $isList = array_is_list($optionsArray);

        foreach ($optionsArray as $key => $option) {
            if (is_array($option)) {
                $normalized[] = [
                    'value' => $option['value'] ?? '',
                    'label' => $option['label'] ?? (string) ($option['value'] ?? ''),
                    'logo' => $option['logo'] ?? null,
                    'description' => $option['description'] ?? null,
                ];
            } elseif ($option instanceof \BackedEnum) {
                $normalized[] = [
                    'value' => $option->value,
                    'label' => method_exists($option, 'label') ? $option->label() : $option->name,
                ];
            } elseif ($isList) {
                $normalized[] = [
                    'value' => $option,
                    'label' => (string) $option,
                ];
            } else {
                $normalized[] = [
                    'value' => $key,
                    'label' => (string) $option,
                ];
            }
        }
    }

    $forceSearchable = $searchable === true || $searchable === 'true' || $searchable === 1 || $searchable === '1';
    $forceNative = $searchable === false || $searchable === 'false' || $searchable === 0 || $searchable === '0';

    $wrapperClass = 'flex min-w-0 flex-col w-full '
        .($label || $hint || $hasError ? 'gap-2 ' : 'gap-0 ')
        .($label && ! $isCompact ? 'min-h-[4.5rem] ' : '');
@endphp

<div {{ $attributes->only('class')->merge(['class' => $wrapperClass]) }} data-enhance-select>
    @if ($label)
        <label for="{{ $id }}" class="{{ $isCompact ? 'text-xs font-semibold text-muted uppercase tracking-wide' : 'text-sm font-semibold text-ink' }} shrink-0">
            {{ $label }}
            @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="relative {{ $isCompact ? 'min-h-10' : 'min-h-12' }} shrink-0 w-full" style="contain: layout">
        <select
            name="{{ $name }}"
            id="{{ $id }}"
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-help" @endif
            @if ($forceSearchable)
                data-searchable="true"
            @elseif ($forceNative)
                data-searchable="false"
            @else
                data-searchable-threshold="{{ (int) $searchableThreshold }}"
            @endif
            @if ($isCompact) data-compact="true" @endif
            data-i18n-search="{{ __('body.search') }}…"
            data-i18n-loading="{{ __('body.loading') }}"
            data-i18n-no-match="{{ __('body.no_matching_options') }}"
            data-i18n-no-options="{{ __('body.no_options_available') }}"
            data-i18n-showing="{{ __('body.showing_options') }}"
            data-i18n-page="{{ __('body.page_of') }}"
            data-i18n-previous="{{ __('body.previous') }}"
            data-i18n-next="{{ __('body.next') }}"
            data-i18n-clear="{{ __('body.clear') }}"
            data-i18n-placeholder="{{ $placeholderLabel }}"
            {{ $attributes->except(['class', 'id'])->merge(['class' => $controlClass]) }}
            style="min-height: {{ $isCompact ? '2.5rem' : '3rem' }}"
        >
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @else
                @unless ($hidePlaceholder)
                    <option value="">{{ $placeholderLabel }}</option>
                @endunless
                @foreach ($normalized as $option)
                    <option
                        value="{{ $option['value'] }}"
                        @if (! empty($option['logo'])) data-logo="{{ $option['logo'] }}" @endif
                        @if (! empty($option['description'])) data-description="{{ $option['description'] }}" @endif
                        @selected((string) $value === (string) $option['value'])
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
            @endif
        </select>
        <span data-select-spinner hidden class="absolute inset-y-0 right-3 sm:right-4 z-10 hidden items-center pointer-events-none" aria-hidden="true">
            <svg class="h-4 w-4 animate-spin text-brand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </span>
    </div>

    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert">{{ $errorMessage }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-help" class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
