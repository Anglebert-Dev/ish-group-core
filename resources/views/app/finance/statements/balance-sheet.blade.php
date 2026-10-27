<x-layouts.app title="{{ __('body.balance_sheet') }}">
    <x-common.page-header :title="__('body.balance_sheet')">
        <x-slot:actions>
            <x-common.button href="{{ route('finance.statements.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap gap-3 items-end">
        <x-common.input type="date" name="as_of" :label="__('body.as_of')" :value="$as_of" />
        <x-common.button type="submit" size="sm">{{ __('body.filter') }}</x-common.button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach ([
            ['title' => __('body.assets'), 'rows' => $assets, 'total' => $asset_total],
            ['title' => __('body.liabilities'), 'rows' => $liabilities, 'total' => $liability_total],
            ['title' => __('body.equity'), 'rows' => $equity, 'total' => $equity_total, 'retained' => $retained_earnings],
        ] as $section)
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-line font-semibold">{{ $section['title'] }}</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @forelse ($section['rows'] as $row)
                            <tr>
                                <td class="px-5 py-3">{{ $row['account']->label() }}</td>
                                <td class="px-5 py-3 text-right">{{ number_format($row['amount'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-5 py-6 text-center text-muted">—</td></tr>
                        @endforelse
                        @isset($section['retained'])
                            <tr>
                                <td class="px-5 py-3">{{ __('body.retained_earnings') }}</td>
                                <td class="px-5 py-3 text-right">{{ number_format($section['retained'], 0) }}</td>
                            </tr>
                        @endisset
                        <tr class="font-semibold border-t border-line">
                            <td class="px-5 py-3">{{ __('body.total') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($section['total'], 0) }} RWF</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</x-layouts.app>
