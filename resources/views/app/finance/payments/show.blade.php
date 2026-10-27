<x-layouts.app title="{{ $payment->payment_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$payment->payment_number"
            :subtitle="__('body.details')"
            :back-href="route('finance.payments.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$payment->status->label()" />
            </x-slot:meta>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.date')" :value="$payment->payment_date->format('Y-m-d')" />
            <x-common.details.summary-item :label="__('body.amount')" :value="number_format($payment->amount, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.method')" :value="$payment->method->label()" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$payment->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.details.section :title="__('body.overview')" icon="M3 10h18M7 15h1m4 0h1m-7 4h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
            <x-common.details.grid>
                <x-common.details.field :label="__('body.date')">{{ $payment->payment_date->format('Y-m-d') }}</x-common.details.field>
                <x-common.details.field :label="__('body.amount')">{{ number_format($payment->amount, 0) }} RWF</x-common.details.field>
                <x-common.details.field :label="__('body.method')">{{ $payment->method->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$payment->status->label()" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.booking')">{{ $payment->booking?->booking_number ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.invoice')">{{ $payment->invoice?->invoice_number ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.expense')">{{ $payment->expense?->expense_number ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.supplier')">{{ $payment->supplier?->name ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.description')" :span="2">{{ $payment->description ?? '—' }}</x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
