<x-layouts.app title="{{ __('body.expenses') }}">
    <x-common.page-header :title="__('body.expenses')" :description="__('body.expenses_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.expenses.export.pdf', request()->query())"
                :excel="route('finance.expenses.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.expenses.create') }}" size="sm">+ {{ __('body.add_expense') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.supplier') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.account') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.amount') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($expenses as $expense)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium whitespace-nowrap">
                            <a href="{{ route('finance.expenses.show', $expense) }}" class="hover:text-brand">{{ $expense->expense_number }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $expense->supplier?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $expense->account?->code }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ number_format($expense->amount, 0) }} RWF</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$expense->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('finance.expenses.show', $expense) }}" class="text-brand font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('body.no_expenses_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$expenses" />
    </div>
</x-layouts.app>
