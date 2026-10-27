<x-layouts.app title="{{ __('body.trial_balance') }}">
    <x-common.page-header :title="__('body.trial_balance')">
        <x-slot:actions>
            <x-common.button href="{{ route('finance.statements.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-end">
        <x-common.input type="date" name="from" :label="__('body.from')" :value="$from" />
        <x-common.input type="date" name="to" :label="__('body.to')" :value="$to" />
        <x-common.button type="submit" size="sm">{{ __('body.filter') }}</x-common.button>
    </form>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.account') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.debit') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.credit') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.closing') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-5 py-3.5">{{ $row['account']->label() }}</td>
                        <td class="px-5 py-3.5 text-right">{{ number_format($row['debit'], 0) }}</td>
                        <td class="px-5 py-3.5 text-right">{{ number_format($row['credit'], 0) }}</td>
                        <td class="px-5 py-3.5 text-right">{{ number_format($row['closing'], 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('body.no_statement_data') }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="border-t border-line font-semibold">
                        <td class="px-5 py-3">{{ __('body.total') }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($total_debit, 0) }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($total_credit, 0) }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
        </div>
    </div>
</x-layouts.app>
