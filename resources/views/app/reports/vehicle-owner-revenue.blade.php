<x-layouts.app title="{{ __('body.vehicle_owner_revenue') }}">
    <x-common.page-header :title="__('body.vehicle_owner_revenue')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-reports.date-filter-form
        :from="$from" :to="$to"
        :exportPdf="route('reports.export.pdf', ['report' => 'vehicle-owner-revenue', 'from' => $from, 'to' => $to])"
        :exportExcel="route('reports.export.excel', ['report' => 'vehicle-owner-revenue', 'from' => $from, 'to' => $to])"
    />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.owner') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.vehicles_count') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.income_split') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.gross_revenue') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.owner_payout') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.company_retained') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $row['owner']->fullName() }}</td>
                            <td class="px-5 py-3.5 text-right">{{ $row['vehicle_count'] }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $row['owner']->splitLabel() }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format((float) $row['gross_revenue'], 0) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format((float) $row['owner_payout'], 0) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format((float) $row['company_retained'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-line">
                            <td class="px-5 py-3" colspan="3">{{ __('body.company_owned_vehicles') }}</td>
                            <td class="px-5 py-3 text-right" colspan="3">{{ number_format((float) $company_owned_revenue, 0) }}</td>
                        </tr>
                        <tr class="border-t border-line font-semibold">
                            <td class="px-5 py-3" colspan="3">{{ __('body.grand_total') }}</td>
                            <td class="px-5 py-3 text-right" colspan="3">{{ number_format((float) $grand_total, 0) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-layouts.app>
