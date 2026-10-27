<x-layouts.app title="{{ __('body.fleet_performance') }}">
    <x-common.page-header :title="__('body.fleet_performance')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-reports.date-filter-form
        :from="$from" :to="$to"
        :exportPdf="route('reports.export.pdf', ['report' => 'fleet-performance', 'from' => $from, 'to' => $to])"
        :exportExcel="route('reports.export.excel', ['report' => 'fleet-performance', 'from' => $from, 'to' => $to])"
    />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.category') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.trips') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.total_revenue') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.maintenance_cost') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.fuel_cost') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.net_contribution') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $row['vehicle']->name() }} — {{ $row['vehicle']->plate_number }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $row['vehicle']->category?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['trips']) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['revenue'], 0) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['maintenance_cost'], 0) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['fuel_cost'], 0) }}</td>
                            <td class="px-5 py-3.5 text-right font-semibold {{ $row['net'] < 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($row['net'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-line font-semibold">
                            <td class="px-5 py-3" colspan="2">{{ __('body.total') }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['trips']) }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['revenue'], 0) }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['maintenance_cost'], 0) }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['fuel_cost'], 0) }}</td>
                            <td class="px-5 py-3 text-right">{{ number_format($totals['net'], 0) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-layouts.app>
