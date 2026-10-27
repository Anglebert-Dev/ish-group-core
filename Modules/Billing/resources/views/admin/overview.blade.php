@php use Modules\Billing\Support\Money; @endphp
<x-layouts.app :title="__('billing::billing.title')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.title')" :description="__('billing::billing.overview_description')" />

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <x-dashboard.stat-card :label="__('billing::billing.mrr')" :value="Money::format($mrr)" :hint="__('billing::billing.mrr_hint')" />
        <x-dashboard.stat-card :label="__('billing::billing.revenue_month')" :value="Money::format($revenueThisMonth)" :href="route('superadmin.billing.payments.index', ['status' => 'approved'])" />
        <x-dashboard.stat-card :label="__('billing::billing.outstanding')" :value="Money::format($outstanding)" :href="route('superadmin.billing.invoices.index', ['status' => 'open'])" />
        <x-dashboard.stat-card :label="__('billing::billing.churn')" :value="$churn.'%'" :hint="__('billing::billing.last_30_days')" />
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach (\Modules\Billing\Enums\SubscriptionStatus::cases() as $status)
            <a href="{{ route('superadmin.billing.subscriptions.index', ['status' => $status->value]) }}" class="bg-white border border-line rounded-xl p-4 hover:border-brand/40 transition-colors">
                <x-common.badge :color="$status->color()">{{ $status->label() }}</x-common.badge>
                <p class="font-display font-bold text-2xl text-ink mt-2">{{ $statusCounts[$status->value] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white border border-warning/40 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-line">
                <h2 class="font-display font-bold text-ink">{{ __('billing::billing.awaiting_approval') }}</h2>
                <a href="{{ route('superadmin.billing.payments.index') }}" class="text-sm font-medium text-brand">{{ __('body.view_all') }}</a>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($pendingPayments as $payment)
                    <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">{{ $payment->business?->name }}</p>
                            <p class="text-muted">{{ $payment->invoice?->number }} · {{ $payment->method->label() }}</p>
                        </div>
                        <span class="font-semibold whitespace-nowrap">{{ Money::format($payment->amount, $payment->invoice?->currency) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted text-sm">{{ __('billing::billing.nothing_to_approve') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.recent_payments') }}</h2></div>
            <ul class="divide-y divide-line">
                @forelse ($recentPayments as $payment)
                    <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-ink truncate">{{ $payment->business?->name }}</p>
                            <p class="text-muted">{{ $payment->invoice?->number }} · {{ $payment->reviewed_at?->format('d M Y') }}</p>
                        </div>
                        <span class="font-semibold text-success whitespace-nowrap">{{ Money::format($payment->amount, $payment->invoice?->currency) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted text-sm">{{ __('billing::billing.no_payments') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
