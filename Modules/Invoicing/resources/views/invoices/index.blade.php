<x-layouts.app :title="__('invoicing::invoicing.invoices')">
    <x-common.page-header :title="__('invoicing::invoicing.invoices')" :description="__('invoicing::invoicing.owed_total', ['amount' => money($owed)])">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('invoicing.invoices.export.pdf', request()->query())"
                :excel="route('invoicing.invoices.export.excel', request()->query())"
            />
            @if (\App\Helpers\AuthHelper::can('create invoicing.invoice'))
                <x-common.button href="{{ route('invoicing.invoices.create') }}" size="sm">+ {{ __('invoicing::invoicing.add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('invoicing.invoices.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('invoicing::invoicing.search_placeholder')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'open' => __('invoicing::invoicing.open')] + collect(\Modules\Invoicing\Enums\InvoiceStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-common.input name="from" type="date" :label="__('body.audit_from')" :value="$filters['from'] ?? ''" compact />
        <x-common.input name="to" type="date" :label="__('body.audit_to')" :value="$filters['to'] ?? ''" compact />
        <x-common.select name="overdue" :label="__('invoicing::invoicing.overdue')" :value="$filters['overdue'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), '1' => __('invoicing::invoicing.overdue_only')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('invoicing::invoicing.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('invoicing::invoicing.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('invoicing::invoicing.invoice_date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('invoicing::invoicing.due_date') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.total') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('invoicing::invoicing.balance') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 whitespace-nowrap"><a href="{{ route('invoicing.invoices.show', $invoice) }}" class="font-medium text-ink hover:text-brand">{{ $invoice->number }}</a></td>
                        <td class="px-5 py-3.5">{{ $invoice->customer?->displayName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap {{ $invoice->isOverdue() ? 'text-danger font-semibold' : '' }}">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right">{{ money($invoice->total) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right font-semibold text-ink">{{ money($invoice->balance()) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.badge :color="$invoice->status->color()">{{ $invoice->status->label() }}</x-common.badge>
                            @if ($invoice->isOverdue())<x-common.badge color="danger">{{ __('invoicing::invoicing.overdue') }}</x-common.badge>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('invoicing::invoicing.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$invoices" />
    </div>
</x-layouts.app>
