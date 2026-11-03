{{-- PIN always; the extra code only when the last attempt asked for it. --}}
@php $stepUp = session('step_up'); @endphp
@if (isset($hasPin) && ! $hasPin)
    <div class="rounded-xl border border-warning/40 bg-warning/5 p-3 text-sm">
        {{ __('injongepay::injongepay.set_pin_first') }} <a href="{{ route('injongepay.security') }}" class="text-brand font-semibold">{{ __('injongepay::injongepay.pin_set') }}</a>
    </div>
@endif
<x-common.input name="pin" type="password" inputmode="numeric" maxlength="5" autocomplete="off" :label="__('injongepay::injongepay.your_pin')" required />
@if ($stepUp)
    <div class="rounded-xl border border-warning/40 bg-warning/5 p-3 space-y-2">
        <p class="text-sm font-semibold text-ink">{{ __('injongepay::injongepay.step_up_needed_'.$stepUp['method']) }}</p>
        <ul class="text-xs text-muted list-disc pl-4">
            @foreach ($stepUp['reasons'] as $reason)<li>{{ __('injongepay::injongepay.step_up_reason_'.$reason) }}</li>@endforeach
        </ul>
        <x-common.input name="code" inputmode="numeric" maxlength="8" autocomplete="one-time-code" :label="__('injongepay::injongepay.step_up_code')" required autofocus />
    </div>
@endif
<p class="text-xs text-muted"><a href="{{ route('injongepay.security') }}" class="text-brand">{{ __('injongepay::injongepay.manage_pin') }}</a></p>
