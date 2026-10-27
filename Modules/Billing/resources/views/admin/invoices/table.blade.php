@php use Modules\Billing\Support\Money; @endphp
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.invoice') }}</th>
                @if ($showBusiness ?? true)<th class="px-5 py-3 font-semibold">{{ __('body.business') }}</th>@endif
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.issued') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.due') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.total') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($invoices as $invoice)
                <tr class="hover:bg-surface">
                    <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $invoice->number }}</td>
                    @if ($showBusiness ?? true)<td class="px-5 py-3.5">{{ $invoice->business?->name }}</td>@endif
                    <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $invoice->issued_at?->format('d M Y') }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap {{ $invoice->isOverdue() ? 'text-danger font-medium' : 'text-muted' }}">{{ $invoice->due_at?->format('d M Y') }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ Money::format($invoice->total, $invoice->currency) }}</td>
                    <td class="px-5 py-3.5"><x-common.badge :color="$invoice->isOverdue() ? 'danger' : $invoice->status->color()">{{ $invoice->isOverdue() ? __('billing::billing.overdue') : $invoice->status->label() }}</x-common.badge></td>
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        <x-common.action-button :href="route('superadmin.billing.invoices.show', $invoice)">{{ __('body.manage') }}</x-common.action-button>
                        <x-common.action-button :href="route('superadmin.billing.invoices.pdf', $invoice)" color="muted">PDF</x-common.action-button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('billing::billing.no_invoices') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
