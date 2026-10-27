<x-layouts.app title="{{ __('body.bookings_summary') }}">
    <x-common.page-header :title="__('body.bookings_summary')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-reports.date-filter-form
        :from="$from" :to="$to"
        :exportPdf="route('reports.export.pdf', ['report' => 'bookings-summary', 'from' => $from, 'to' => $to])"
        :exportExcel="route('reports.export.excel', ['report' => 'bookings-summary', 'from' => $from, 'to' => $to])"
    />

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
        <x-dashboard.stat-card :label="__('body.total_bookings')" :value="$total_bookings" />
        <x-dashboard.stat-card :label="__('body.total_revenue')" :value="number_format($total_revenue, 0).' RWF'" />
    </div>

    @foreach ([
        ['title' => __('body.status'), 'rows' => $by_status, 'field' => 'status'],
        ['title' => __('body.rental_type'), 'rows' => $by_rental_type, 'field' => 'rental_type'],
        ['title' => __('body.payment_option'), 'rows' => $by_payment_option, 'field' => 'payment_option'],
    ] as $table)
        <div class="bg-white border border-line rounded-xl overflow-hidden mb-4">
            <p class="font-semibold text-ink px-5 py-3 border-b border-line">{{ $table['title'] }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                            <th class="px-5 py-3 font-semibold">{{ $table['title'] }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('body.bookings_count') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('body.total_revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($table['rows'] as $row)
                            <tr>
                                <td class="px-5 py-3.5">{{ $row->{$table['field']}->label() }}</td>
                                <td class="px-5 py-3.5 text-right">{{ number_format($row->aggregate) }}</td>
                                <td class="px-5 py-3.5 text-right">{{ number_format((float) $row->revenue, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-layouts.app>
