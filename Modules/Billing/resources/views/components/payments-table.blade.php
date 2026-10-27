@php use Modules\Billing\Support\Money; @endphp
@props(['payments', 'admin' => false])
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                @if ($admin)<th class="px-5 py-3 font-semibold">{{ __('body.business') }}</th>@endif
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.invoice') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.method') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.reference') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('billing::billing.paid_on') }}</th>
                <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                @if ($admin)<th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>@endif
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($payments as $payment)
                <tr class="align-top">
                    @if ($admin)<td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $payment->business?->name }}</td>@endif
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        @if ($payment->invoice)
                            <a href="{{ $admin ? route('superadmin.billing.invoices.show', $payment->invoice) : route('billing.invoices.show', $payment->invoice) }}" class="text-brand hover:text-brand-dark">{{ $payment->invoice->number }}</a>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ Money::format($payment->amount, $payment->invoice?->currency) }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $payment->method->label() }}</td>
                    <td class="px-5 py-3.5">{{ $payment->reference ?: '—' }}</td>
                    <td class="px-5 py-3.5 whitespace-nowrap">{{ $payment->paid_on?->format('d M Y') }}</td>
                    <td class="px-5 py-3.5">
                        <x-common.badge :color="$payment->status->color()">{{ $payment->status->label() }}</x-common.badge>
                        @if ($payment->rejection_reason)
                            <p class="text-xs text-danger mt-1 max-w-xs">{{ $payment->rejection_reason }}</p>
                        @endif
                    </td>
                    @if ($admin)
                        <td class="px-5 py-3.5">
                            <div class="flex flex-col items-start gap-1.5">
                                @if ($payment->proof_path)
                                    <x-common.action-button :href="route('superadmin.billing.payments.proof', $payment)" color="muted" target="_blank">{{ __('billing::billing.view_proof') }}</x-common.action-button>
                                @endif
                                @if ($payment->status->value === 'pending')
                                    <x-common.action-button :action="route('superadmin.billing.payments.approve', $payment)" color="success"
                                        data-confirm data-confirm-title="{{ __('billing::billing.approve_payment') }}?"
                                        data-confirm-message="{{ Money::format($payment->amount, $payment->invoice?->currency) }} · {{ $payment->business?->name }}">
                                        {{ __('body.approve') }}
                                    </x-common.action-button>
                                    <details class="relative">
                                        <summary class="list-none inline-flex items-center rounded-lg border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-danger hover:bg-red-50 cursor-pointer [&::-webkit-details-marker]:hidden">{{ __('billing::billing.reject') }}</summary>
                                        <form action="{{ route('superadmin.billing.payments.reject', $payment) }}" method="POST" class="mt-2 w-64 space-y-2">
                                            @csrf
                                            <x-common.textarea name="reason" :rows="2" :placeholder="__('billing::billing.reject_reason')" required />
                                            <x-common.button type="submit" size="sm">{{ __('billing::billing.reject') }}</x-common.button>
                                        </form>
                                    </details>
                                @elseif ($payment->reviewer)
                                    <p class="text-xs text-muted">{{ __('billing::billing.reviewed_by', ['name' => $payment->reviewer->name]) }}</p>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $admin ? 8 : 6 }}" class="px-5 py-10 text-center text-muted">{{ __('billing::billing.no_payments') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
