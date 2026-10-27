@props([
    'name' => 'phone',
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'error' => null,
    'disabled' => false,
])

@php
    $label = $label ?? __('body.phone_number');
    $placeholder = $placeholder ?? __('body.phone_national_placeholder');
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-');
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $errorKey = preg_replace('/\.+/', '.', trim($errorKey, '.')) ?? $errorKey;
    $errorMessage = $error ?? ($errors->first($errorKey) ?: null);
    $hasError = filled($errorMessage);
    $current = old($errorKey, old($name, $value));
    $split = \App\Libraries\PhoneCountryLibrary::split($current);
    $selected = \App\Libraries\PhoneCountryLibrary::findByIso($split['iso']);
    $countries = \App\Libraries\PhoneCountryLibrary::countries();
    $uid = 'phone-'.\Illuminate\Support\Str::random(6);
@endphp

<div
    {{ $attributes->except('id')->class('flex min-w-0 flex-col gap-2 w-full') }}
    data-phone-input
    data-phone-uid="{{ $uid }}"
    data-default-iso="{{ $selected['iso'] }}"
    data-phone-max-digits="{{ $selected['digits'] }}"
>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}" data-phone-full @if ($required) required @endif>

    <div class="relative">
        <div
            class="flex items-stretch rounded-xl border bg-white shadow-sm transition-all duration-200 overflow-hidden
                {{ $hasError ? 'border-danger' : 'border-line' }}
                focus-within:ring-2 focus-within:ring-brand/20 focus-within:border-brand"
            data-phone-shell
        >
            <button
                type="button"
                class="flex items-center gap-1.5 px-3 sm:px-3.5 shrink-0 border-r border-line bg-surface/60 hover:bg-surface transition-colors {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                data-phone-toggle
                aria-haspopup="listbox"
                aria-expanded="false"
                aria-controls="{{ $uid }}-list"
                @if ($disabled) disabled @endif
            >
                <span class="text-lg leading-none" data-phone-flag aria-hidden="true">{{ $selected['flag'] }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-muted transition-transform" data-phone-chevron fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <label class="flex-1 min-w-0 px-3.5 py-2 cursor-text" for="{{ $uid }}-national">
                <span class="block text-[11px] font-semibold uppercase tracking-wide text-muted leading-none mb-1">
                    {{ $label }}@if ($required)<span class="text-danger">*</span>@endif
                </span>
                <span class="flex items-center gap-1.5 min-w-0">
                    <span class="text-sm font-bold text-ink tabular-nums shrink-0" data-phone-dial>{{ $selected['dial'] }}</span>
                    <input
                        type="tel"
                        id="{{ $uid }}-national"
                        value="{{ $split['national'] }}"
                        inputmode="tel"
                        autocomplete="tel-national"
                        data-phone-national
                        placeholder="{{ $placeholder }}"
                        class="w-full min-w-0 bg-transparent border-0 p-0 text-sm font-bold text-ink placeholder:text-muted/70 placeholder:font-medium focus:outline-none focus:ring-0"
                        @if ($disabled) disabled @endif
                        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                    >
                </span>
            </label>
        </div>

        <div
            id="{{ $uid }}-list"
            class="absolute z-40 left-0 right-0 mt-2 rounded-xl border border-line bg-white shadow-[0_16px_40px_rgba(15,23,42,0.12)] overflow-hidden hidden"
            data-phone-dropdown
            hidden
            role="listbox"
        >
            <div class="p-2 border-b border-line">
                <label class="flex items-center gap-2 h-10 px-3 rounded-lg bg-surface border border-line">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                    </svg>
                    <input
                        type="search"
                        data-phone-search
                        placeholder="{{ __('body.phone_search_countries') }}"
                        class="w-full bg-transparent border-0 p-0 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-0"
                        autocomplete="off"
                    >
                </label>
            </div>
            <ul class="max-h-56 overflow-y-auto py-1" data-phone-options>
                @foreach ($countries as $country)
                    <li>
                        <button
                            type="button"
                            class="w-full flex items-center gap-3 px-3.5 py-2.5 text-left text-sm hover:bg-surface transition-colors {{ $country['iso'] === $selected['iso'] ? 'bg-brand-light/50' : '' }}"
                            data-phone-option
                            data-iso="{{ $country['iso'] }}"
                            data-dial="{{ $country['dial'] }}"
                            data-flag="{{ $country['flag'] }}"
                            data-name="{{ $country['name'] }}"
                            data-digits="{{ $country['digits'] }}"
                            role="option"
                            aria-selected="{{ $country['iso'] === $selected['iso'] ? 'true' : 'false' }}"
                        >
                            <span class="text-lg leading-none" aria-hidden="true">{{ $country['flag'] }}</span>
                            <span class="flex-1 min-w-0 text-ink font-medium truncate">{{ $country['name'] }}</span>
                            <span class="text-muted tabular-nums shrink-0">{{ $country['dial'] }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-brand shrink-0 {{ $country['iso'] === $selected['iso'] ? '' : 'invisible' }}" data-phone-check fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </li>
                @endforeach
            </ul>
            <p class="px-3.5 py-3 text-xs text-muted hidden" data-phone-empty hidden>{{ __('body.phone_no_countries') }}</p>
        </div>
    </div>

    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert">{{ $errorMessage }}</p>
    @endif
</div>
