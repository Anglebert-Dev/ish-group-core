<tr><td colspan="{{ $span }}" class="px-3 pt-3 text-right text-muted">{{ __('inventory::inventory.subtotal') }}</td><td class="px-3 pt-3 text-right">{{ money($invoice->subtotal) }}</td></tr>
@if ((float) $invoice->discount > 0)
    <tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right text-muted">{{ __('inventory::inventory.discount') }}</td><td class="px-3 pt-1 text-right">− {{ money($invoice->discount) }}</td></tr>
@endif
@if ((float) $invoice->tax > 0)
    <tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right text-muted">{{ __('invoicing::invoicing.tax_named', ['rate' => rtrim(rtrim(number_format($invoice->tax_rate, 3), '0'), '.')]) }}</td><td class="px-3 pt-1 text-right">{{ money($invoice->tax) }}</td></tr>
@endif
<tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right font-bold text-ink">{{ __('inventory::inventory.total') }}</td><td class="px-3 pt-1 text-right font-bold text-ink">{{ money($invoice->total) }}</td></tr>
<tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right text-muted">{{ __('invoicing::invoicing.paid') }}</td><td class="px-3 pt-1 text-right">{{ money($invoice->paid) }}</td></tr>
<tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right font-bold text-ink">{{ __('invoicing::invoicing.balance') }}</td><td class="px-3 pt-1 text-right font-bold {{ $invoice->balance() > 0 ? 'text-danger' : 'text-success' }}">{{ money($invoice->balance()) }}</td></tr>
