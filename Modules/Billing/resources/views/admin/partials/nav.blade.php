@php
    $tabs = [
        ['label' => __('body.overview'), 'route' => 'superadmin.billing.overview', 'active' => 'superadmin.billing.overview'],
        ['label' => __('billing::billing.packages'), 'route' => 'superadmin.billing.packages.index', 'active' => 'superadmin.billing.packages.*'],
        ['label' => __('billing::billing.subscriptions'), 'route' => 'superadmin.billing.subscriptions.index', 'active' => 'superadmin.billing.subscriptions.*'],
        ['label' => __('billing::billing.invoices'), 'route' => 'superadmin.billing.invoices.index', 'active' => 'superadmin.billing.invoices.*'],
        ['label' => __('billing::billing.payments'), 'route' => 'superadmin.billing.payments.index', 'active' => 'superadmin.billing.payments.*'],
        ['label' => __('billing::billing.coupons'), 'route' => 'superadmin.billing.coupons.index', 'active' => 'superadmin.billing.coupons.*'],
        ['label' => __('body.settings'), 'route' => 'superadmin.billing.settings.index', 'active' => 'superadmin.billing.settings.*'],
    ];
    $pending = \Modules\Billing\Models\Payment::query()->where('status', 'pending')->count();
@endphp

<x-superadmin.nav />

{{-- Billing sub-tabs --}}
<nav class="flex flex-wrap gap-1.5 mb-6">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['active']); @endphp
        <a href="{{ route($tab['route']) }}"
           class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium border transition-colors {{ $active ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line hover:border-brand hover:text-brand' }}">
            {{ $tab['label'] }}
            @if ($tab['route'] === 'superadmin.billing.payments.index' && $pending > 0)
                <span class="rounded-full px-1.5 text-xs {{ $active ? 'bg-white/25' : 'bg-amber-100 text-amber-800' }}">{{ $pending }}</span>
            @endif
        </a>
    @endforeach
</nav>
