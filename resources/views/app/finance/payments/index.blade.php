<x-layouts.app title="{{ __('body.payments') }}">
    <x-common.page-header :title="__('body.payments')" :description="__('body.payments_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.payments.export.pdf', request()->query())"
                :excel="route('finance.payments.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.payments.create') }}" size="sm">+ {{ __('body.add_payment') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.method') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium whitespace-nowrap">
                            <a href="{{ route('finance.payments.show', $payment) }}" class="hover:text-brand">{{ $payment->payment_number }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $payment->payment_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ number_format($payment->amount, 0) }} RWF</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $payment->method->label() }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$payment->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('finance.payments.show', $payment) }}" class="text-brand font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_payments_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$payments" />
    </div>
</x-layouts.app>
