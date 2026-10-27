@php use Modules\Billing\Support\Money; @endphp
{{-- Shown on the staff sign-up form when coming from /pricing. --}}
<input type="hidden" name="package" value="{{ $package->uuid }}">
<div class="rounded-xl border border-brand/30 bg-brand-light px-4 py-3 text-sm">
    <p class="text-brand-dark">
        {{ __('billing::billing.selected_plan') }}
        <strong>{{ $package->name }}</strong> —
        {{ $package->isFree() ? __('billing::billing.free') : Money::format($package->price).' / '.$package->intervalLabel() }}
        @if ($package->trial_days > 0)
            · {{ trans_choice('billing::billing.trial_days', $package->trial_days, ['count' => $package->trial_days]) }}
        @endif
    </p>
    <a href="{{ route('pricing') }}" class="text-xs font-semibold text-brand hover:text-brand-dark">{{ __('billing::billing.change_selection') }}</a>
</div>
