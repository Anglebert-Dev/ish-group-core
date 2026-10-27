@php use Modules\Billing\Support\Money; @endphp
{{-- Invoice document (business and superadmin views). --}}
<div class="bg-white border border-line rounded-xl overflow-hidden">
    <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-line">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('billing::billing.invoice') }}</p>
            <p class="font-display font-bold text-xl text-ink mt-1">{{ $invoice->number }}</p>
            <div class="mt-2"><x-common.badge :color="$invoice->isOverdue() ? 'danger' : $invoice->status->color()">{{ $invoice->isOverdue() ? __('billing::billing.overdue') : $invoice->status->label() }}</x-common.badge></div>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <dt class="text-muted">{{ __('billing::billing.issued') }}</dt><dd class="text-ink">@datetime($invoice->issued_at)</dd>
            <dt class="text-muted">{{ __('billing::billing.due') }}</dt><dd class="text-ink">@datetime($invoice->due_at)</dd>
            @if ($invoice->paid_at)
                <dt class="text-muted">{{ __('billing::billing.paid_on') }}</dt><dd class="text-ink">@datetime($invoice->paid_at)</dd>
            @endif
            <dt class="text-muted">{{ __('billing::billing.billed_to') }}</dt>
            <dd class="text-ink">
                {{ $invoice->billed_to['name'] ?? $invoice->business?->name }}
                @if (! empty($invoice->billed_to['tax_id']))<br><span class="text-muted">TIN {{ $invoice->billed_to['tax_id'] }}</span>@endif
            </dd>
        </dl>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.description') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.amount') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td class="px-5 py-3 text-ink">{{ $line->description }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap {{ (float) $line->amount < 0 ? 'text-success' : 'text-ink' }}">{{ Money::format($line->amount, $invoice->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="p-5 sm:p-6 border-t border-line flex justify-end">
        <dl class="w-full sm:w-80 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-muted">{{ __('billing::billing.subtotal') }}</dt><dd>{{ Money::format($invoice->subtotal, $invoice->currency) }}</dd></div>
            @if ((float) $invoice->discount > 0)
                <div class="flex justify-between"><dt class="text-muted">{{ __('billing::billing.discount') }}</dt><dd class="text-success">− {{ Money::format($invoice->discount, $invoice->currency) }}</dd></div>
            @endif
            @if ((float) $invoice->credit > 0)
                <div class="flex justify-between"><dt class="text-muted">{{ __('billing::billing.credit') }}</dt><dd class="text-success">− {{ Money::format($invoice->credit, $invoice->currency) }}</dd></div>
            @endif
            @if ((float) $invoice->tax > 0)
                <div class="flex justify-between"><dt class="text-muted">{{ $invoice->tax_label ?: __('billing::billing.tax') }} ({{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%{{ $invoice->tax_inclusive ? ', '.__('billing::billing.included') : '' }})</dt><dd>{{ Money::format($invoice->tax, $invoice->currency) }}</dd></div>
            @endif
            <div class="flex justify-between font-semibold text-ink text-base pt-2 border-t border-line"><dt>{{ __('billing::billing.total') }}</dt><dd>{{ Money::format($invoice->total, $invoice->currency) }}</dd></div>
            @if ((float) $invoice->amount_paid > 0)
                <div class="flex justify-between"><dt class="text-muted">{{ __('billing::billing.amount_paid') }}</dt><dd>{{ Money::format($invoice->amount_paid, $invoice->currency) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt>{{ __('billing::billing.balance_due') }}</dt><dd>{{ Money::format(Money::fromCents($invoice->balanceCents()), $invoice->currency) }}</dd></div>
            @endif
        </dl>
    </div>
</div>
