<x-layouts.app title="{{ __('body.profit_and_loss') }}">
    <x-common.page-header :title="__('body.profit_and_loss')">
        <x-slot:actions>
            <x-common.button href="{{ route('finance.statements.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-end">
        <x-common.input type="date" name="from" :label="__('body.from')" :value="$from" />
        <x-common.input type="date" name="to" :label="__('body.to')" :value="$to" />
        <x-common.button type="submit" size="sm">{{ __('body.filter') }}</x-common.button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-line font-semibold">{{ __('body.revenue') }}</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($revenue as $row)
                        <tr>
                            <td class="px-5 py-3">{{ $row['account']->label() }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($row['amount'], 0) }} RWF</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-muted text-center" colspan="2">—</td></tr>
                    @endforelse
                    <tr class="font-semibold border-t border-line"><td class="px-5 py-3">{{ __('body.total') }}</td><td class="px-5 py-3 text-right">{{ number_format($revenue_total, 0) }} RWF</td></tr>
                </tbody>
            </table>
        </div>
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-line font-semibold">{{ __('body.expenses') }}</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($expenses as $row)
                        <tr>
                            <td class="px-5 py-3">{{ $row['account']->label() }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($row['amount'], 0) }} RWF</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-muted text-center" colspan="2">—</td></tr>
                    @endforelse
                    <tr class="font-semibold border-t border-line"><td class="px-5 py-3">{{ __('body.total') }}</td><td class="px-5 py-3 text-right">{{ number_format($expense_total, 0) }} RWF</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 bg-brand-light border border-brand/20 rounded-xl px-5 py-4 flex justify-between items-center">
        <span class="font-semibold text-ink">{{ __('body.net_income') }}</span>
        <span class="font-semibold text-brand-dark text-lg">{{ number_format($net_income, 0) }} RWF</span>
    </div>
</x-layouts.app>
