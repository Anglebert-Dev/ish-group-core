@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'error' => null,
    'disabled' => false,
    'readonly' => false,
    'size' => null,
    'compact' => false,
    'strength' => false,
    'confirmFor' => null,
    'amount' => false,
])

@php
    $id = $attributes->get('id') ?: 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name), '-');
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $errorMessage = $error ?? ($errors->first($errorKey) ?: null);
    $hasError = filled($errorMessage);
    $isPassword = $type === 'password';
    $showStrength = $isPassword && $strength;
    $isAmount = (bool) $amount;
    $isDate = in_array($type, ['date', 'datetime-local', 'month', 'time', 'week'], true);
    $useCompact = (bool) $compact || $size === 'sm';
    $describedBy = collect([
        $hasError ? $id.'-error' : null,
        (! $hasError && $hint) ? $id.'-help' : null,
        $showStrength ? $id.'-strength' : null,
        $confirmFor ? $id.'-match' : null,
    ])->filter()->implode(' ');

    if ($isAmount) {
        $type = 'text';
        if ($value !== null && $value !== '') {
            $clean = str_replace(',', '', (string) $value);
            if (is_numeric($clean)) {
                $parts = explode('.', $clean, 2);
                $formatted = number_format((float) $parts[0], 0, '.', ',');
                if (isset($parts[1]) && $parts[1] !== '' && rtrim($parts[1], '0') !== '') {
                    $formatted .= '.'.rtrim($parts[1], '0');
                }
                $value = $formatted;
            }
        }
    }

    $exceptAttrs = ['class', 'id'];
    if ($isAmount) {
        $exceptAttrs = array_merge($exceptAttrs, ['type', 'step', 'min', 'max']);
    }

    if ($isDate) {
        $controlClass = 'date-field '
            .($useCompact ? 'date-field--sm ' : '')
            .($hasError ? 'date-field--error ' : '')
            .($disabled ? 'opacity-50 cursor-not-allowed ' : '')
            .($readonly ? 'bg-surface ' : '');
    } else {
        $controlClass = 'h-12 w-full rounded-xl border text-sm bg-white shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand placeholder:text-muted '
            .($isPassword ? 'pl-4 pr-12' : 'px-4').' '
            .($isAmount ? 'tabular-nums ' : '')
            .($hasError ? 'border-danger' : 'border-line').' '
            .($disabled ? 'opacity-50 cursor-not-allowed' : '').' '
            .($readonly ? 'bg-surface' : '');
    }

    $labelClass = $useCompact
        ? 'text-[11px] font-semibold uppercase tracking-wide text-muted'
        : 'text-sm font-semibold text-ink';

    $rules = [
        ['key' => 'min', 'label' => __('body.password_rule_min')],
        ['key' => 'upper', 'label' => __('body.password_rule_upper')],
        ['key' => 'lower', 'label' => __('body.password_rule_lower')],
        ['key' => 'number', 'label' => __('body.password_rule_number')],
        ['key' => 'symbol', 'label' => __('body.password_rule_symbol')],
    ];
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex min-w-0 flex-col '.($useCompact ? 'gap-1.5' : 'gap-2').' w-full']) }}>
    @if ($label)
        <label for="{{ $id }}" class="{{ $labelClass }}">
            {{ $label }}
            @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="{{ $isDate ? 'date-field-wrap' : 'relative min-h-12' }} w-full">
        @if ($isDate)
            <svg class="date-field-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ $value }}"
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($readonly) readonly @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($isAmount) inputmode="decimal" data-amount-input autocomplete="off" @endif
            {{ $attributes->except($exceptAttrs)->merge(['class' => $controlClass]) }}
            @if ($isPassword) data-password-field @endif
            @if ($showStrength) data-password-strength @endif
            @if ($confirmFor) data-password-confirm-for="{{ $confirmFor }}" @endif
        >

        @if ($isPassword)
            <button
                type="button"
                tabindex="-1"
                data-password-toggle
                class="absolute inset-y-0 right-0 flex items-center justify-center pr-3 text-muted hover:text-ink transition-colors"
                aria-label="{{ __('body.toggle_password') }}"
            >
                <svg data-password-show xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg data-password-hide xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            </button>
        @endif
    </div>

    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs text-danger" role="alert" @if ($showStrength) data-password-server-error @endif>{{ $errorMessage }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-help" class="text-xs text-muted">{{ $hint }}</p>
    @endif

    @if ($showStrength)
        <div
            id="{{ $id }}-strength"
            class="password-strength hidden space-y-2"
            data-password-strength-panel
            data-for="{{ $id }}"
            hidden
        >
            <p data-password-strength-message class="text-xs text-danger" hidden>{{ __('body.password_strength_hint') }}</p>
            <p data-password-strength-ok class="text-xs text-success font-medium" hidden>{{ __('body.password_strong') }}</p>
            <ul class="space-y-1.5" role="list">
                @foreach ($rules as $rule)
                    <li
                        data-password-rule="{{ $rule['key'] }}"
                        class="flex items-center gap-2 text-xs text-muted transition-colors"
                    >
                        <span data-rule-dot class="inline-block h-1.5 w-1.5 rounded-full bg-muted/50 shrink-0" aria-hidden="true"></span>
                        <span data-rule-label>{{ $rule['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($confirmFor)
        <p
            id="{{ $id }}-match"
            class="text-xs hidden"
            data-password-match-message
            data-match-ok="{{ __('body.password_match') }}"
            data-match-bad="{{ __('body.password_mismatch') }}"
            hidden
        ></p>
    @endif
</div>
