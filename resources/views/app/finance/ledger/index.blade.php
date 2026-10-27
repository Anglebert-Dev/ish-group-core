<x-layouts.app title="{{ __('body.general_ledger') }}">
    <x-common.page-header :title="__('body.general_ledger')" :description="__('body.general_ledger_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.ledger.export.pdf', request()->query())"
                :excel="route('finance.ledger.export.excel', request()->query())"
            />
        </x-slot:actions>
    </x-common.page-header>

    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <x-common.input type="date" name="from" :label="__('body.from')" :value="$filters['from'] ?? ''" />
        <x-common.input type="date" name="to" :label="__('body.to')" :value="$filters['to'] ?? ''" />
        <x-common.input name="reference_type" :label="__('body.reference_type')" :value="$filters['reference_type'] ?? ''" />
        <x-common.select
            name="account_id"
            :label="__('body.account')"
            :value="$filters['account_id'] ?? ''"
            :placeholder="__('body.all')"
            searchable
        >
            <option value="">{{ __('body.all') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(($filters['account_id'] ?? '') == $account->id)>{{ $account->label() }}</option>
            @endforeach
        </x-common.select>
        <div class="flex items-end">
            <x-common.button type="submit" size="sm">{{ __('body.filter') }}</x-common.button>
        </div>
    </form>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.reference') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.description') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($ledgers as $ledger)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $ledger->transaction_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5 font-mono whitespace-nowrap">
                            <a href="{{ route('finance.ledger.show', $ledger) }}" class="hover:text-brand">{{ $ledger->reference }}</a>
                        </td>
                        <td class="px-5 py-3.5">{{ $ledger->description }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$ledger->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('finance.ledger.show', $ledger) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_ledger_entries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$ledgers" />
    </div>
</x-layouts.app>
