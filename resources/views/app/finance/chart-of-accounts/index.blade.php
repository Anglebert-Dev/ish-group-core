<x-layouts.app title="{{ __('body.chart_of_accounts') }}">
    <x-common.page-header :title="__('body.chart_of_accounts')" :description="__('body.chart_of_accounts_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.chart-of-accounts.export.pdf', request()->query())"
                :excel="route('finance.chart-of-accounts.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.account-assignments.index') }}" variant="secondary" size="sm">{{ __('body.account_assignments') }}</x-common.button>
            <x-common.button href="{{ route('finance.chart-of-accounts.create') }}" size="sm">+ {{ __('body.add_account') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.account_code') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.account_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.account_type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.posting') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.balance') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($accounts as $account)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-mono text-ink whitespace-nowrap">
                            <a href="{{ route('finance.chart-of-accounts.show', $account) }}" class="hover:text-brand">{{ $account->code }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap {{ $account->is_posting ? '' : 'font-semibold' }}">{{ $account->name }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $account->account_type->label() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $account->is_posting ? __('body.yes') : __('body.no') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ number_format($account->current_balance, 0) }} RWF</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$account->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('finance.chart-of-accounts.show', $account) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            @if ($account->is_posting)
                                <span class="text-line mx-1.5">|</span>
                                <a href="{{ route('finance.chart-of-accounts.edit', $account) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('body.no_accounts_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$accounts" />
    </div>
</x-layouts.app>
