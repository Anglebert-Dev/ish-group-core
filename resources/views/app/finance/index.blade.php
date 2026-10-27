<x-layouts.app title="{{ __('body.finance') }}">
    <x-common.page-header :title="__('body.finance')" :description="__('body.finance_home_description')" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['label' => __('body.revenue'), 'value' => $totals['revenue']],
            ['label' => __('body.expenses'), 'value' => $totals['expense']],
            ['label' => __('body.bank_cash'), 'value' => $totals['bank']],
            ['label' => __('body.accounts_receivable'), 'value' => $totals['ar']],
        ] as $card)
            <div class="bg-white border border-line rounded-xl p-5">
                <p class="text-xs uppercase tracking-wide text-muted font-semibold">{{ $card['label'] }}</p>
                <p class="text-2xl font-semibold text-ink mt-2">{{ number_format($card['value'], 0) }} <span class="text-sm font-normal text-muted">RWF</span></p>
                <p class="text-xs text-muted mt-1">{{ $totals['from'] }} → {{ $totals['to'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ([
            ['route' => 'finance.invoices.index', 'label' => __('body.invoices'), 'desc' => __('body.invoices_description')],
            ['route' => 'finance.payments.index', 'label' => __('body.payments'), 'desc' => __('body.payments_description')],
            ['route' => 'finance.expenses.index', 'label' => __('body.expenses'), 'desc' => __('body.expenses_description')],
            ['route' => 'finance.suppliers.index', 'label' => __('body.suppliers'), 'desc' => __('body.suppliers_description')],
            ['route' => 'finance.chart-of-accounts.index', 'label' => __('body.chart_of_accounts'), 'desc' => __('body.chart_of_accounts_description')],
            ['route' => 'finance.ledger.index', 'label' => __('body.general_ledger'), 'desc' => __('body.general_ledger_description')],
            ['route' => 'finance.statements.index', 'label' => __('body.financial_statements'), 'desc' => __('body.financial_statements_description')],
            ['route' => 'finance.account-assignments.index', 'label' => __('body.account_assignments'), 'desc' => __('body.account_assignments_description')],
        ] as $link)
            <a href="{{ route($link['route']) }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition block">
                <h3 class="font-semibold text-ink">{{ $link['label'] }}</h3>
                <p class="text-sm text-muted mt-1">{{ $link['desc'] }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
