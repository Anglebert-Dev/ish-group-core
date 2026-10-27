<x-layouts.app title="{{ $expense->expense_number }}">
    <div data-details-page>
        <x-common.details.header
            :title="$expense->expense_number"
            :subtitle="$expense->description"
            :back-href="route('finance.expenses.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$expense->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($expense->status === \App\Enums\ExpenseStatus::Draft)
                    <x-common.button href="{{ route('finance.expenses.edit', $expense) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
                    <form action="{{ route('finance.expenses.post', $expense) }}" method="POST" class="inline">@csrf
                        <x-common.button type="submit" size="sm">{{ __('body.post_to_ledger') }}</x-common.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.date')" :value="$expense->expense_date->format('Y-m-d')" />
            <x-common.details.summary-item :label="__('body.amount')" :value="number_format($expense->amount, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.supplier')" :value="$expense->supplier?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$expense->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.details.section :title="__('body.overview')" icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z">
            <x-common.details.grid>
                <x-common.details.field :label="__('body.date')">{{ $expense->expense_date->format('Y-m-d') }}</x-common.details.field>
                <x-common.details.field :label="__('body.amount')">{{ number_format($expense->amount, 0) }} RWF</x-common.details.field>
                <x-common.details.field :label="__('body.supplier')">{{ $expense->supplier?->name ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.account')">{{ $expense->account?->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.paid_now')">{{ $expense->paid ? __('body.yes') : __('body.no') }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$expense->status->label()" />
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
