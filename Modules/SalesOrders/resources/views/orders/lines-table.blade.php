<div class="overflow-x-auto">
<table class="w-full text-sm">
    <thead>
        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
            <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.line_item') }}</th>
            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_quantity') }}</th>
            @isset($available)<th class="px-3 py-2.5 font-semibold text-right">{{ __('salesorders::orders.available_in_store', ['store' => $order->store?->name]) }}</th>@endisset
            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_price') }}</th>
            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_total') }}</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-line">
        @foreach ($order->lines as $line)
            <tr>
                <td class="px-3 py-3">
                    <p class="font-medium text-ink">{{ $line->variant?->displayName() }}</p>
                    @if ($line->description)<p class="text-xs text-muted">{{ $line->description }}</p>@endif
                </td>
                <td class="px-3 py-3 text-right whitespace-nowrap">@include('inventory::partials.qty', ['value' => $line->quantity]) {{ $line->variant?->item?->unit }}</td>
                @isset($available)
                    @php $have = $available[$line->variant_id] ?? 0; @endphp
                    <td class="px-3 py-3 text-right whitespace-nowrap {{ $have < $line->quantity ? 'text-danger font-semibold' : 'text-muted' }}">@include('inventory::partials.qty', ['value' => $have])</td>
                @endisset
                <td class="px-3 py-3 text-right whitespace-nowrap">{{ money($line->unit_price) }}</td>
                <td class="px-3 py-3 text-right whitespace-nowrap font-semibold text-ink">{{ money($line->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot class="border-t border-line">
        @php $span = isset($available) ? 4 : 3; @endphp
        <tr><td colspan="{{ $span }}" class="px-3 pt-3 text-right text-muted">{{ __('inventory::inventory.subtotal') }}</td><td class="px-3 pt-3 text-right">{{ money($order->subtotal) }}</td></tr>
        @if ((float) $order->discount > 0)
            <tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right text-muted">{{ __('inventory::inventory.discount') }}</td><td class="px-3 pt-1 text-right">− {{ money($order->discount) }}</td></tr>
        @endif
        <tr><td colspan="{{ $span }}" class="px-3 pt-1 text-right font-bold text-ink">{{ __('inventory::inventory.total') }}</td><td class="px-3 pt-1 text-right font-bold text-ink">{{ money($order->total) }}</td></tr>
    </tfoot>
</table>
</div>
