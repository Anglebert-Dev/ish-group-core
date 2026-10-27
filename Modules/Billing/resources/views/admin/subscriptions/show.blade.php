@php use Modules\Billing\Support\Money; @endphp
<x-layouts.app :title="$subscription->name()">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="$subscription->business?->name" :description="__('billing::billing.subscription').' · '.$subscription->name()">
        <x-slot:actions>
            @if ($subscription->business)
                <x-common.button href="{{ route('superadmin.businesses.show', $subscription->business) }}" variant="secondary" size="sm">{{ __('body.view_business') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-common.info-card :title="__('billing::billing.subscription')" :rows="[
            [__('billing::billing.package'), $subscription->name()],
            [__('body.status'), $subscription->status->label()],
            [__('billing::billing.price'), Money::format($subscription->price()).' / '.$subscription->interval()->label($subscription->intervalCount())],
        ]" />
        <x-common.info-card :title="__('billing::billing.period')" :rows="[
            [__('billing::billing.period_start'), $subscription->current_period_start?->format('d M Y')],
            [__('billing::billing.paid_until'), $subscription->current_period_end?->format('d M Y')],
            [__('billing::billing.trial_ends'), $subscription->trial_ends_at?->format('d M Y')],
            [__('billing::billing.grace_ends'), $subscription->grace_ends_at?->format('d M Y')],
        ]" />
        <x-common.info-card :title="__('billing::billing.extras')" :rows="[
            [__('billing::billing.scheduled_package'), $subscription->scheduledPackage?->name],
            [__('billing::billing.coupon'), $subscription->coupon ? $subscription->coupon->code.' — '.$subscription->coupon->summary() : null],
            [__('billing::billing.cancel_at_end'), $subscription->cancel_at_period_end ? __('body.yes') : __('body.no')],
            [__('billing::billing.modules'), implode(', ', $subscription->snapshot['modules'] ?? []) ?: null],
        ]" />
    </div>

    @if ($subscription->isCurrent())
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <form action="{{ route('superadmin.billing.subscriptions.extend', $subscription) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf @method('PUT')
                <div>
                    <h2 class="font-display font-bold text-ink">{{ __('billing::billing.extend') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('billing::billing.extend_hint') }}</p>
                </div>
                <x-common.input name="until" type="date" :label="__('billing::billing.paid_until')" :value="$subscription->current_period_end?->toDateString()" required />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>

            <form action="{{ route('superadmin.billing.subscriptions.cancel', $subscription) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-4">
                @csrf
                <div>
                    <h2 class="font-display font-bold text-danger">{{ __('billing::billing.cancel_now') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('billing::billing.cancel_now_hint') }}</p>
                </div>
                <button type="submit" class="inline-flex items-center rounded-lg bg-danger hover:opacity-90 text-white font-semibold text-sm px-4 py-2"
                    data-confirm data-confirm-title="{{ __('billing::billing.cancel_now') }}?" data-confirm-variant="danger">{{ __('billing::billing.cancel_now') }}</button>
            </form>
        </div>
    @endif

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.invoices') }}</h2></div>
        @include('billing::admin.invoices.table', ['invoices' => $subscription->invoices, 'showBusiness' => false])
    </div>
</x-layouts.app>
