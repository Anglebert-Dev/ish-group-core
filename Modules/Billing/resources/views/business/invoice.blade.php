<x-layouts.app :title="$invoice->number">
    <x-common.page-header :title="__('billing::billing.invoice').' '.$invoice->number">
        <x-slot:actions>
            <x-common.button href="{{ route('billing.index') }}" variant="secondary" size="sm">← {{ __('billing::billing.my_subscription') }}</x-common.button>
            <x-common.button href="{{ route('billing.invoices.pdf', $invoice) }}" variant="secondary" size="sm">{{ __('billing::billing.download_pdf') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            @include('billing::partials.invoice-body')

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('billing::billing.payments') }}</h2></div>
                <x-billing::payments-table :payments="$invoice->payments" />
            </div>
        </div>

        <div class="space-y-6">
            @if (filled($instructions))
                <div class="bg-white border border-line rounded-xl p-5">
                    <h2 class="font-display font-bold text-ink mb-2">{{ __('billing::billing.how_to_pay') }}</h2>
                    <p class="text-sm text-body leading-relaxed">{!! nl2br(e($instructions)) !!}</p>
                </div>
            @endif

            @if ($invoice->isOpen() && \App\Helpers\AuthHelper::can('create billing.payment'))
                @include('billing::partials.payment-form', [
                    'action' => route('billing.invoices.pay', $invoice),
                    'title' => __('billing::billing.i_have_paid'),
                    'hint' => __('billing::billing.i_have_paid_hint'),
                    'submitLabel' => __('billing::billing.submit_payment'),
                ])
            @endif
        </div>
    </div>
</x-layouts.app>
