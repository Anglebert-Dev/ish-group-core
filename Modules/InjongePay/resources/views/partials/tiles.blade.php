@php use Modules\InjongePay\Support\Money; @endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach ([
        ['payments_received', $summary['payments_count'].' · '.Money::format($summary['payments_gross'])],
        ['fees', Money::format($summary['payments_fee'])],
        ['net', Money::format($summary['payments_net'])],
        ['reversed_by_bank', Money::format($summary['payments_reversed'])],
        ['cashouts_paid', $summary['cashouts_paid_count'].' · '.Money::format($summary['cashouts_paid'])],
        ['cashouts_open', Money::format($summary['cashouts_open'])],
        ['transfers_in_out', Money::format($summary['transfers_in']).' / '.Money::format($summary['transfers_out'])],
        ['balance_now', Money::format($summary['available']).' + '.Money::format($summary['pending'])],
    ] as [$key, $value])
        <div class="bg-card border border-line rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('injongepay::injongepay.tile_'.$key) }}</p>
            <p class="text-lg font-display font-bold text-ink mt-1">{{ $value }}</p>
        </div>
    @endforeach
</div>
