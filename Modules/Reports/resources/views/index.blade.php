<x-layouts.app :title="__('reports::reports.reports')">
    <x-common.page-header :title="__('reports::reports.reports')" :description="__('reports::reports.reports_description')">
        @if ($result)
            <x-slot:actions>
                <x-common.export-buttons
                    :pdf="route('reports.export', ['format' => 'pdf'] + request()->query() + ['report' => $report])"
                    :excel="route('reports.export', ['format' => 'excel'] + request()->query() + ['report' => $report])"
                />
            </x-slot:actions>
        @endif
    </x-common.page-header>

    @if ($available === [])
        <div class="bg-white border border-line rounded-xl p-10 text-center text-muted">{{ __('reports::reports.none') }}</div>
    @else
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach ($available as $key)
                <a href="{{ route('reports.index', ['report' => $key, 'from' => $from, 'to' => $to]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors {{ $report === $key ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line hover:border-brand hover:text-brand' }}">{{ __('reports::reports.'.$key) }}</a>
            @endforeach
        </div>

        <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="report" value="{{ $report }}">
            <div class="w-44"><x-common.input name="from" type="date" :label="__('reports::reports.from')" :value="$from" compact /></div>
            <div class="w-44"><x-common.input name="to" type="date" :label="__('reports::reports.to')" :value="$to" compact /></div>
            <x-common.button type="submit" variant="secondary" size="sm">{{ __('reports::reports.show') }}</x-common.button>
            @if ($report === 'stock_value')<p class="text-xs text-muted">{{ __('reports::reports.stock_value_note') }}</p>@endif
        </form>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
            @foreach ($result['totals'] as $label => $value)
                <div class="bg-white border border-line rounded-xl p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ $label }}</p>
                    <p class="text-xl font-display font-bold text-ink mt-1">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    @foreach ($result['table']->headings as $i => $heading)
                        <th class="px-5 py-3 font-semibold {{ $i > 0 ? 'text-right' : '' }}">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($result['table']->rows as $row)
                        <tr>
                            @foreach ($row as $i => $cell)
                                <td class="px-5 py-3 {{ $i > 0 ? 'text-right whitespace-nowrap' : 'font-medium text-ink' }}">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($result['table']->headings) }}" class="px-5 py-10 text-center text-muted">{{ __('reports::reports.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @endif
</x-layouts.app>
