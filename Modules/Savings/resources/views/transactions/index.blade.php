<x-layouts.app :title="__('savings::savings.transactions')">
    <x-common.page-header :title="__('savings::savings.transactions')">
        <x-slot:actions>
            <x-common.export-buttons :pdf="route('savings.transactions.export', ['format' => 'pdf'] + request()->query())" :excel="route('savings.transactions.export', ['format' => 'excel'] + request()->query())" />
            @if (\App\Helpers\AuthHelper::can('create savings.transaction'))
                <x-common.button href="{{ route('savings.transactions.create') }}" size="sm">+ {{ __('savings::savings.new') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('savings.transactions.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('savings::savings.search_placeholder')" compact />
        <x-common.select name="type" :label="__('savings::savings.type')" :value="$filters['type'] ?? ''" compact hide-placeholder
            :options="['' => __('savings::savings.all_types')] + collect(\Modules\Savings\Services\Ledger::TYPES)->mapWithKeys(fn ($t) => [$t => __('savings::savings.type_'.$t)])->all()" />
        <x-common.select name="account" :label="__('savings::savings.account')" :value="$filters['account'] ?? ''" compact hide-placeholder
            :options="['' => __('savings::savings.all_accounts')] + $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all()" />
        <x-common.select name="category" :label="__('savings::savings.category')" :value="$filters['category'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + $categories->mapWithKeys(fn ($c) => [$c->uuid => $c->fullName()])->all()" />
        <x-common.input name="from" type="date" :label="__('savings::savings.from')" :value="$filters['from'] ?? ''" compact />
        <x-common.input name="to" type="date" :label="__('savings::savings.to')" :value="$filters['to'] ?? ''" compact />
        <x-common.input name="min" :label="__('savings::savings.min_amount')" :value="$filters['min'] ?? ''" compact />
        <x-common.input name="max" :label="__('savings::savings.max_amount')" :value="$filters['max'] ?? ''" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'active' => __('savings::savings.active'), 'reversed' => __('savings::savings.reversed')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        @include('savings::transactions.table', ['transactions' => $transactions])
        <x-common.pagination :paginator="$transactions" />
    </div>
</x-layouts.app>
