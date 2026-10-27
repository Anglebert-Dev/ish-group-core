@props([
    'action',
    'method' => 'POST',
    'steps' => [],
    'enctype' => null,
    'maxWidth' => 'w-full',
    'startStep' => 1,
])

@php
    $httpMethod = strtoupper($method);
    $spoof = ! in_array($httpMethod, ['GET', 'POST'], true);
    $formMethod = $spoof ? 'POST' : $httpMethod;
    $startStep = max(1, min((int) $startStep, max(1, count($steps))));
@endphp

<form
    action="{{ $action }}"
    method="{{ $formMethod }}"
    novalidate
    data-form-wizard
    data-wizard-step-count="{{ count($steps) }}"
    data-wizard-start="{{ $startStep }}"
    data-msg-required="{{ __('body.field_required') }}"
    data-msg-dates="{{ __('body.dates_incomplete') }}"
    data-msg-return="{{ __('body.return_after_pickup') }}"
    data-msg-password-weak="{{ __('body.password_strength_hint') }}"
    @if ($enctype) enctype="{{ $enctype }}" @endif
    {{ $attributes->merge(['class' => $maxWidth.' space-y-5']) }}
>
    @csrf
    @if ($spoof)
        @method($httpMethod)
    @endif

    <nav class="w-full overflow-x-auto pb-1" aria-label="{{ __('body.form_steps') }}" data-wizard-stepper>
        <ol class="flex items-stretch gap-1 sm:gap-2 min-w-0">
            @foreach ($steps as $index => $step)
                @php $stepNum = $index + 1; @endphp
                <li class="flex-1 min-w-[4.5rem] sm:min-w-[6rem]">
                    <button
                        type="button"
                        data-wizard-step-btn="{{ $stepNum }}"
                        @if ($stepNum > 1) disabled @endif
                        class="w-full flex flex-col items-center gap-1 px-1 py-2 rounded-lg text-center transition-all
                               {{ $stepNum === 1 ? 'bg-brand text-white shadow-md' : 'bg-white border border-line text-muted opacity-60 cursor-not-allowed' }}"
                    >
                        <span
                            data-wizard-step-badge
                            class="flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-full text-xs sm:text-sm font-bold shrink-0
                                   {{ $stepNum === 1 ? 'bg-white/20' : 'bg-surface' }}"
                        >{{ $stepNum }}</span>
                        <span class="text-[10px] sm:text-xs font-medium leading-tight line-clamp-2">{{ $step['label'] }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    {{ $slot }}
</form>
