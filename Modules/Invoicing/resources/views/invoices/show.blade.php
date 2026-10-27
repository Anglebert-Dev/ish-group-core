@php
    $can = fn (string $permission) => \App\Helpers\AuthHelper::can($permission);
    $cancelled = $invoice->status === \Modules\Invoicing\Enums\InvoiceStatus::Cancelled;
@endphp
<x-layouts.app :title="$invoice->number">
    <x-common.page-header :title="$invoice->number" :description="$invoice->customer?->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ route('invoicing.invoices.index') }}" variant="secondary" size="sm">← {{ __('invoicing::invoicing.invoices') }}</x-common.button>
            <x-common.button href="{{ route('invoicing.invoices.pdf', $invoice) }}" size="sm">{{ __('invoicing::invoicing.download_pdf') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.info-card :title="__('invoicing::invoicing.invoice')" :rows="[
                    [__('invoicing::invoicing.invoice_date'), $invoice->invoice_date->format('d/m/Y')],
                    [__('invoicing::invoicing.due_date'), $invoice->due_date?->format('d/m/Y')],
                    [__('invoicing::invoicing.store'), $invoice->store?->name],
                    [__('invoicing::invoicing.sales_order'), $salesOrder?->number],
                ]" />
                <x-common.info-card :title="__('invoicing::invoicing.customer')" :rows="[
                    [__('body.name'), $invoice->customer?->displayName()],
                    [__('body.phone'), $invoice->customer?->phone],
                    [__('body.email_address'), $invoice->customer?->email],
                    [__('body.customer_tax_id'), $invoice->customer?->tax_id],
                ]" />
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('invoicing::invoicing.lines') }}</h2>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                            <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.line_item') }}</th>
                            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_quantity') }}</th>
                            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_price') }}</th>
                            <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoice->lines as $line)
                            <tr>
                                <td class="px-3 py-3"><p class="font-medium text-ink">{{ $line->variant?->displayName() }}</p>@if ($line->description)<p class="text-xs text-muted">{{ $line->description }}</p>@endif</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">@include('inventory::partials.qty', ['value' => $line->quantity]) {{ $line->variant?->item?->unit }}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">{{ money($line->unit_price) }}</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap font-semibold text-ink">{{ money($line->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-line">@include('invoicing::invoices.totals', ['span' => 3])</tfoot>
                </table>
                </div>
                <p class="text-xs text-muted">{{ $invoice->stock_issued ? __('invoicing::invoicing.stock_issued', ['store' => $invoice->store?->name]) : __('invoicing::invoicing.stock_with_order') }}</p>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('invoicing::invoicing.payments') }}</h2></div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @forelse ($invoice->payments as $payment)
                            <tr class="{{ $payment->isReversed() ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3 whitespace-nowrap font-medium text-ink">{{ $payment->number }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $payment->paid_on->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">{{ $payment->method->label() }}@if ($payment->reference)<span class="text-muted"> · {{ $payment->reference }}</span>@endif</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap font-semibold {{ $payment->isReversed() ? 'line-through' : 'text-success' }}">{{ money($payment->amount) }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($payment->isReversed())
                                        <x-common.badge color="muted" title="{{ $payment->reverse_reason }}">{{ __('invoicing::invoicing.reversed') }}</x-common.badge>
                                    @elseif ($can('delete invoicing.payment'))
                                        <details class="inline-block text-left">
                                            <summary class="cursor-pointer text-xs font-semibold text-danger">{{ __('invoicing::invoicing.reverse') }}</summary>
                                            <form action="{{ route('invoicing.invoices.payments.reverse', [$invoice, $payment]) }}" method="POST" class="mt-2 flex gap-2">
                                                @csrf
                                                <input type="text" name="reason" required placeholder="{{ __('invoicing::invoicing.reverse_reason') }}" class="rounded-lg border border-line px-2 py-1 text-xs">
                                                <button type="submit" class="text-xs font-semibold text-danger">{{ __('invoicing::invoicing.reverse') }}</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-6 text-center text-muted">{{ __('invoicing::invoicing.payments_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-common.badge>
                </div>
                <p class="text-2xl font-display font-bold {{ $invoice->balance() > 0 ? 'text-danger' : 'text-success' }}">{{ money($invoice->balance()) }}</p>
                <p class="text-xs text-muted">{{ __('invoicing::invoicing.balance') }}</p>
                @if ($cancelled)
                    <p class="text-sm text-muted pt-2">{{ __('invoicing::invoicing.credit_note', ['number' => $invoice->credit_note_number]) }} · {{ $invoice->cancel_reason }}</p>
                @endif
                @if ($salesOrder)
                    <a href="{{ route('salesorders.orders.show', $salesOrder) }}" class="text-sm font-semibold text-brand">{{ $salesOrder->number }} →</a>
                @endif
            </div>

            @if (! $cancelled && $invoice->balance() > 0 && $can('create invoicing.payment'))
                <form action="{{ route('invoicing.invoices.pay', $invoice) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('invoicing::invoicing.payment_add') }}</h2>
                    <x-common.input name="amount" type="number" step="any" min="0" :max="$invoice->balance()" :label="__('invoicing::invoicing.amount')" :value="old('amount', $invoice->balance())" required />
                    <x-common.input name="paid_on" type="date" :label="__('invoicing::invoicing.paid_on')" :value="old('paid_on', now()->toDateString())" required />
                    <x-common.select name="method" :label="__('invoicing::invoicing.method')" :value="old('method', 'cash')" :options="$methods" hide-placeholder required />
                    <x-common.input name="reference" :label="__('invoicing::invoicing.reference')" :value="old('reference')" :hint="__('invoicing::invoicing.reference_hint')" />
                    <x-common.input name="note" :label="__('invoicing::invoicing.note')" :value="old('note')" />
                    <x-common.button type="submit" class="w-full">{{ __('invoicing::invoicing.payment_add') }}</x-common.button>
                </form>
            @endif

            @if (! $cancelled && $can('delete invoicing.invoice'))
                <form action="{{ route('invoicing.invoices.cancel', $invoice) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('invoicing::invoicing.action_cancel') }}</h2>
                    <p class="text-xs text-muted">{{ __('invoicing::invoicing.cancel_hint') }}</p>
                    <x-common.input name="reason" :label="__('invoicing::invoicing.cancel_reason')" required />
                    <input type="hidden" name="return_stock" value="0">
                    <x-common.checkbox name="return_stock" value="1" :label="__('invoicing::invoicing.return_stock', ['store' => $invoice->store?->name])" :checked="true" />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('invoicing::invoicing.action_cancel') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
