<x-layouts.app title="{{ $ledger->reference }}">
    <div data-details-page>
        <x-common.details.header
            :title="$ledger->reference"
            :subtitle="$ledger->description"
            :back-href="route('finance.ledger.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$ledger->status->label()" />
            </x-slot:meta>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.date')" :value="$ledger->transaction_date->format('Y-m-d')" />
            <x-common.details.summary-item :label="__('body.reference_type')" :value="$ledger->reference_type" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$ledger->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <div class="space-y-6">
            <x-common.details.section :title="__('body.overview')" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.date')">{{ $ledger->transaction_date->format('Y-m-d') }}</x-common.details.field>
                    <x-common.details.field :label="__('body.reference_type')">{{ $ledger->reference_type }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="$ledger->status->label()" />
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            <x-common.details.section :title="__('body.details')" icon="M4 6h16M4 10h16M4 14h16M4 18h16" tone="ink">
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                <th class="px-3 py-3 font-semibold">{{ __('body.account') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ __('body.description') }}</th>
                                <th class="px-3 py-3 font-semibold text-right">{{ __('body.debit') }}</th>
                                <th class="px-3 py-3 font-semibold text-right">{{ __('body.credit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($ledger->lines as $line)
                                <tr>
                                    <td class="px-3 py-3.5 whitespace-nowrap">{{ $line->account?->label() }}</td>
                                    <td class="px-3 py-3.5">{{ $line->description ?? '—' }}</td>
                                    <td class="px-3 py-3.5 text-right">{{ $line->debit > 0 ? number_format($line->debit, 0).' RWF' : '—' }}</td>
                                    <td class="px-3 py-3.5 text-right">{{ $line->credit > 0 ? number_format($line->credit, 0).' RWF' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
