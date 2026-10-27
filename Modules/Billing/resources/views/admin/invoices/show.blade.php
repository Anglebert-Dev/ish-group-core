<x-layouts.app :title="$invoice->number">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.invoice').' '.$invoice->number" :description="$invoice->business?->name">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.billing.invoices.pdf', $invoice) }}" variant="secondary" size="sm">{{ __('billing::billing.download_pdf') }}</x-common.button>
            @if ($invoice->subscription)
                <x-common.button href="{{ route('superadmin.billing.subscriptions.show', $invoice->subscription) }}" variant="secondary" size="sm">{{ __('billing::billing.subscription') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            @include('billing::partials.invoice-body')

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.payments') }}</h2></div>
                <x-billing::payments-table :payments="$invoice->payments" admin />
            </div>
        </div>

        @if ($invoice->isOpen())
            <div class="space-y-6">
                @include('billing::partials.payment-form', [
                    'action' => route('superadmin.billing.invoices.record-payment', $invoice),
                    'title' => __('billing::billing.record_payment'),
                    'hint' => __('billing::billing.record_payment_hint'),
                    'submitLabel' => __('billing::billing.record_payment'),
                ])

                <form action="{{ route('superadmin.billing.invoices.void', $invoice) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('billing::billing.void_invoice') }}</h2>
                    <x-common.input name="reason" :label="__('billing::billing.void_reason')" />
                    <button type="submit" class="inline-flex items-center rounded-lg bg-danger hover:opacity-90 text-white font-semibold text-sm px-4 py-2"
                        data-confirm data-confirm-title="{{ __('billing::billing.void_invoice') }}?" data-confirm-variant="danger">{{ __('billing::billing.void_invoice') }}</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>
