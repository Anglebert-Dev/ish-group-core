<div class="bg-white border border-line rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-ink">{{ __('invoicing::invoicing.order_invoices') }}</h2>
        @if ($invoices->where('status', '!=', \Modules\Invoicing\Enums\InvoiceStatus::Cancelled)->isEmpty()
            && $salesOrder->status !== \Modules\SalesOrders\Enums\OrderStatus::Cancelled
            && $salesOrder->status !== \Modules\SalesOrders\Enums\OrderStatus::Pending
            && \App\Helpers\AuthHelper::can('create invoicing.invoice'))
            <x-common.button href="{{ route('invoicing.invoices.create', ['sales_order' => $salesOrder->uuid]) }}" size="sm">{{ __('invoicing::invoicing.order_invoice') }}</x-common.button>
        @endif
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-line">
            @forelse ($invoices as $invoice)
                <tr>
                    <td class="px-5 py-3"><a href="{{ route('invoicing.invoices.show', $invoice) }}" class="font-medium text-ink hover:text-brand">{{ $invoice->number }}</a></td>
                    <td class="px-5 py-3 text-right">{{ money($invoice->total) }}</td>
                    <td class="px-5 py-3 text-right">{{ money($invoice->balance()) }}</td>
                    <td class="px-5 py-3"><x-common.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-common.badge></td>
                </tr>
            @empty
                <tr><td class="px-5 py-6 text-center text-muted">{{ __('invoicing::invoicing.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
