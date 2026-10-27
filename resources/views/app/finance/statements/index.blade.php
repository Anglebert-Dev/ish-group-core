<x-layouts.app title="{{ __('body.financial_statements') }}">
    <x-common.page-header :title="__('body.financial_statements')" :description="__('body.financial_statements_description')" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('finance.statements.trial-balance') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.trial_balance') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.trial_balance_description') }}</p>
        </a>
        <a href="{{ route('finance.statements.profit-loss') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.profit_and_loss') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.profit_and_loss_description') }}</p>
        </a>
        <a href="{{ route('finance.statements.balance-sheet') }}" class="bg-white border border-line rounded-xl p-5 hover:border-brand transition">
            <h3 class="font-semibold text-ink">{{ __('body.balance_sheet') }}</h3>
            <p class="text-sm text-muted mt-1">{{ __('body.balance_sheet_description') }}</p>
        </a>
    </div>
</x-layouts.app>
