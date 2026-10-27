<x-layouts.app title="{{ __('body.invoices') }}">
    <x-common.page-header :title="__('body.invoices')" :description="__('body.invoices_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.invoices.export.pdf', request()->query())"
                :excel="route('finance.invoices.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.invoices.create') }}" size="sm">+ {{ __('body.add_invoice') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium whitespace-nowrap">
                            <a href="{{ route('finance.invoices.show', $invoice) }}" class="hover:text-brand">{{ $invoice->invoice_number }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $invoice->invoice_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $invoice->partyName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ number_format($invoice->total, 0) }} RWF</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$invoice->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('finance.invoices.show', $invoice) }}" class="text-brand font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_invoices_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$invoices" />
    </div>
</x-layouts.app>
