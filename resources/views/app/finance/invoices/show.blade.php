<x-layouts.app title="{{ $invoice->invoice_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$invoice->invoice_number"
            :subtitle="$invoice->partyName()"
            :back-href="route('finance.invoices.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$invoice->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($invoice->status === \App\Enums\InvoiceStatus::Draft)
                    <x-common.button href="{{ route('finance.invoices.edit', $invoice) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
                    <form action="{{ route('finance.invoices.issue', $invoice) }}" method="POST" class="inline">@csrf
                        <x-common.button type="submit" size="sm">{{ __('body.issue_invoice') }}</x-common.button>
                    </form>
                @endif
                @if (in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::Issued], true))
                    <form action="{{ route('finance.invoices.cancel', $invoice) }}" method="POST" class="inline">@csrf
                        <x-common.button type="submit" size="sm" variant="danger">{{ __('body.cancel') }}</x-common.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.date')" :value="$invoice->invoice_date->format('Y-m-d')" />
            <x-common.details.summary-item :label="__('body.booking')" :value="$invoice->booking?->booking_number ?? '—'" />
            <x-common.details.summary-item :label="__('body.total')" :value="number_format($invoice->total, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$invoice->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <div class="space-y-6">
            <x-common.details.section :title="__('body.overview')" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.date')">{{ $invoice->invoice_date->format('Y-m-d') }}</x-common.details.field>
                    <x-common.details.field :label="__('body.booking')">{{ $invoice->booking?->booking_number ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="$invoice->status->label()" />
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            <x-common.details.section :title="__('body.details')" icon="M4 6h16M4 10h16M4 14h16M4 18h16" tone="ink">
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                <th class="px-3 py-3 font-semibold">{{ __('body.description') }}</th>
                                <th class="px-3 py-3 font-semibold text-right">{{ __('body.quantity') }}</th>
                                <th class="px-3 py-3 font-semibold text-right">{{ __('body.unit_price') }}</th>
                                <th class="px-3 py-3 font-semibold text-right">{{ __('body.total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($invoice->lines as $line)
                                <tr>
                                    <td class="px-3 py-3.5">{{ $line->description }}</td>
                                    <td class="px-3 py-3.5 text-right">{{ $line->quantity }}</td>
                                    <td class="px-3 py-3.5 text-right">{{ number_format($line->unit_price, 0) }}</td>
                                    <td class="px-3 py-3.5 text-right">{{ number_format($line->total, 0) }} RWF</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t border-line">
                                <td colspan="3" class="px-3 py-3 text-right">{{ __('body.total') }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($invoice->total, 0) }} RWF</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
