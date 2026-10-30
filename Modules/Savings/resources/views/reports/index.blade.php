<x-layouts.app :title="__('savings::savings.reports')">
    <x-common.page-header :title="__('savings::savings.reports')">
        <x-slot:actions>
            <x-common.export-buttons :pdf="route('savings.reports.export', ['format' => 'pdf'] + request()->query() + ['report' => $report])" :excel="route('savings.reports.export', ['format' => 'excel'] + request()->query() + ['report' => $report])" />
        </x-slot:actions>
    </x-common.page-header>
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach (\Modules\Savings\Services\Reports::KEYS as $key)
            <a href="{{ route('savings.reports', ['report' => $key, 'from' => $from, 'to' => $to]) }}" class="px-3 py-2 rounded-lg text-sm font-semibold border {{ $report === $key ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line' }}">{{ __('savings::savings.report_'.$key) }}</a>
        @endforeach
    </div>
    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="report" value="{{ $report }}">
        <div class="w-44"><x-common.input name="from" type="date" :label="__('savings::savings.from')" :value="$from" compact /></div>
        <div class="w-44"><x-common.input name="to" type="date" :label="__('savings::savings.to')" :value="$to" compact /></div>
        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.show') }}</x-common.button>
    </form>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        @foreach ($result['totals'] as $label => $value)
            <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ $label }}</p><p class="text-lg font-display font-bold">{{ $value }}</p></div>
        @endforeach
    </div>
    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-muted uppercase border-b border-line bg-surface/60">@foreach ($result['table']->headings as $i => $h)<th class="px-5 py-3 {{ $i ? 'text-right' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($result['table']->rows as $row)
                    <tr>@foreach ($row as $i => $cell)<td class="px-5 py-3 {{ $i ? 'text-right whitespace-nowrap' : 'font-medium text-ink' }}">{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($result['table']->headings) }}" class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
