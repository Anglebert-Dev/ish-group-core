<x-layouts.app title="{{ __('body.account_assignments') }}">
    <x-common.page-header :title="__('body.account_assignments')" :description="__('body.account_assignments_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.account-assignments.export.pdf', request()->query())"
                :excel="route('finance.account-assignments.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.chart-of-accounts.index') }}" size="sm" variant="secondary">{{ __('body.chart_of_accounts') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.action') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.slot') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.account') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($assignments as $assignment)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-mono whitespace-nowrap">{{ $assignment->action }}</td>
                        <td class="px-5 py-3.5 font-mono whitespace-nowrap">{{ $assignment->slot }}</td>
                        <td class="px-5 py-3.5">
                            <form method="POST" action="{{ route('finance.account-assignments.update', $assignment) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PUT')
                                <x-common.select
                                    name="chart_of_account_id"
                                    :value="$assignment->chart_of_account_id"
                                    :hide-placeholder="true"
                                    searchable
                                    compact
                                    class="min-w-[16rem] flex-1"
                                >
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected($assignment->chart_of_account_id === $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </x-common.select>
                                <button type="submit" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.save') }}</button>
                            </form>
                        </td>
                        <td class="px-5 py-3.5 text-right text-muted whitespace-nowrap">{{ $assignment->account?->code }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('body.no_assignments_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$assignments" />
    </div>
</x-layouts.app>
