@php
    use Modules\Billing\Support\Money;
    $mode = $preview['mode'];
    $draft = $preview['draft'];
@endphp
<x-layouts.app :title="__('billing::billing.checkout')">
    <x-common.page-header :title="__('billing::billing.checkout')" :description="$package->name">
        <x-slot:actions>
            <x-common.button href="{{ route('billing.plans') }}" variant="secondary" size="sm">← {{ __('billing::billing.choose_plan') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <x-billing::package-card :package="$package" />

        <div class="lg:col-span-2 bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.summary') }}</h2>

            <p class="text-sm text-body">{{ __('billing::billing.mode_'.$mode, [
                'name' => $package->name,
                'current' => $preview['current']?->name(),
                'date' => $preview['effective_at']?->format('d M Y'),
                'days' => $package->trial_days,
            ]) }}</p>

            @if ($draft)
                <dl class="space-y-1.5 text-sm rounded-xl bg-surface border border-line p-4">
                    @foreach ($draft['lines'] as $line)
                        <div class="flex justify-between gap-4"><dt class="text-body">{{ $line['description'] }}</dt><dd class="{{ $line['amount'] < 0 ? 'text-success' : 'text-ink' }} whitespace-nowrap">{{ Money::format(Money::fromCents($line['amount'])) }}</dd></div>
                    @endforeach
                    @if ($draft['totals']->tax > 0)
                        <div class="flex justify-between"><dt class="text-muted">{{ \Modules\Billing\Support\BillingSettings::get('tax_label') ?: __('billing::billing.tax') }}{{ $draft['totals']->taxInclusive ? ' ('.__('billing::billing.included').')' : '' }}</dt><dd>{{ Money::format(Money::fromCents($draft['totals']->tax)) }}</dd></div>
                    @endif
                    <div class="flex justify-between font-semibold text-ink text-base pt-2 border-t border-line"><dt>{{ __('billing::billing.due_now') }}</dt><dd>{{ Money::format(Money::fromCents($draft['totals']->total)) }}</dd></div>
                </dl>
            @endif

            @if ($mode !== 'same')
                <form method="GET" action="{{ route('billing.checkout', $package) }}" class="flex items-end gap-2 max-w-sm">
                    <x-common.input name="coupon" :label="__('billing::billing.coupon_code')" :value="$couponCode" :error="$couponError" compact />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('body.apply') }}</x-common.button>
                </form>

                <form method="POST" action="{{ route('billing.subscribe', $package) }}">
                    @csrf
                    <input type="hidden" name="coupon" value="{{ $couponError ? '' : $couponCode }}">
                    <x-common.button type="submit">{{ __('billing::billing.confirm_'.$mode) }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
