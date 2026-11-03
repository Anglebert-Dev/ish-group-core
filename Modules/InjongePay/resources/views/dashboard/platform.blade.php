@php use Modules\InjongePay\Support\Money; @endphp
@php
    $rows = array_filter([
        $review > 0 ? [__('injongepay::injongepay.dash_review', ['n' => $review]), route('injongepay.platform.cashouts.index', ['status' => 'NEEDS_REVIEW']), 'danger'] : null,
        $items > 0 ? [__('injongepay::injongepay.dash_items', ['n' => $items]), route('injongepay.platform.reconciliation.index'), 'danger'] : null,
        $unallocated > 0 ? [__('injongepay::injongepay.dash_unallocated', ['n' => $unallocated, 'amount' => Money::format($unallocatedTotal)]), route('injongepay.platform.unallocated.index'), 'warning'] : null,
        $waitingCodes > 0 ? [__('injongepay::injongepay.dash_waiting_codes', ['n' => $waitingCodes]), route('injongepay.platform.wallets.index'), 'warning'] : null,
    ]);
@endphp
<div class="bg-card border border-line rounded-xl p-5">
    <h3 class="font-semibold text-ink mb-3">{{ __('injongepay::injongepay.dash_needs_you') }}</h3>
    @forelse ($rows as [$text, $url, $color])
        <a href="{{ $url }}" class="flex items-center gap-3 py-2 border-b border-line last:border-0 text-sm hover:text-brand">
            <span class="h-2 w-2 rounded-full {{ $color === 'danger' ? 'bg-danger' : 'bg-warning' }}"></span>
            <span class="flex-1">{{ $text }}</span>
            <span class="text-brand font-semibold">→</span>
        </a>
    @empty
        <p class="text-sm text-success font-semibold">{{ __('injongepay::injongepay.dash_all_clear') }}</p>
    @endforelse
</div>
