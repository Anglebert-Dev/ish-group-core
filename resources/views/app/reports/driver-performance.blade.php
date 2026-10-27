<x-layouts.app title="{{ __('body.driver_performance') }}">
    <x-common.page-header :title="__('body.driver_performance')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-reports.date-filter-form
        :from="$from" :to="$to"
        :exportPdf="route('reports.export.pdf', ['report' => 'driver-performance', 'from' => $from, 'to' => $to])"
        :exportExcel="route('reports.export.excel', ['report' => 'driver-performance', 'from' => $from, 'to' => $to])"
    />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.driver') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.chauffeur_jobs_count') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.chauffeur_fee_revenue') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.license_expiry') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.compliance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $row['driver']->fullName() }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['jobs']) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row['chauffeur_revenue'], 0) }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $row['driver']->license_expiry?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5">
                                <x-common.badge :color="$row['license_alert_level']->tone()">{{ $row['license_alert_summary'] }}</x-common.badge>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
