<x-layouts.app title="{{ $account->label() }}">
    <div data-details-page>
        <x-common.details.header
            :title="$account->label()"
            :subtitle="$account->account_type->label()"
            :back-href="route('finance.chart-of-accounts.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$account->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($account->is_posting)
                    <x-common.button href="{{ route('finance.chart-of-accounts.edit', $account) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.account_code')" :value="$account->code" />
            <x-common.details.summary-item :label="__('body.account_type')" :value="$account->account_type->label()" />
            <x-common.details.summary-item :label="__('body.balance')" :value="number_format($account->current_balance, 0).' RWF'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$account->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('finance.chart-of-accounts.status', $account)"
            :value="$account->status->value"
            :options="\App\Enums\ChartOfAccountStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update finance.chart_of_account')"
        />


        <x-common.details.section :title="__('body.overview')" icon="M4 6h16M4 10h16M4 14h16M4 18h16">
            <x-common.details.grid>
                <x-common.details.field :label="__('body.account_code')">{{ $account->code }}</x-common.details.field>
                <x-common.details.field :label="__('body.account_name')">{{ $account->name }}</x-common.details.field>
                <x-common.details.field :label="__('body.account_type')">{{ $account->account_type->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.normal_balance')">{{ $account->normal_balance->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.posting')">{{ $account->is_posting ? __('body.yes') : __('body.no') }}</x-common.details.field>
                <x-common.details.field :label="__('body.balance')">{{ number_format($account->current_balance, 0) }} RWF</x-common.details.field>
                <x-common.details.field :label="__('body.parent_account')">{{ $account->parent?->label() ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$account->status->label()" />
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
