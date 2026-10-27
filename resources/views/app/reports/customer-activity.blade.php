<x-layouts.app title="{{ __('body.customer_activity') }}">
    <x-common.page-header :title="__('body.customer_activity')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-reports.date-filter-form
        :from="$from" :to="$to"
        :exportPdf="route('reports.export.pdf', ['report' => 'customer-activity', 'from' => $from, 'to' => $to])"
        :exportExcel="route('reports.export.excel', ['report' => 'customer-activity', 'from' => $from, 'to' => $to])"
    />

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
        <x-dashboard.stat-card :label="__('body.individual_total')" :value="number_format($individual_total, 0).' RWF'" />
        <x-dashboard.stat-card :label="__('body.corporate_total')" :value="number_format($corporate_total, 0).' RWF'" />
        <x-dashboard.stat-card :label="__('body.combined_total')" :value="number_format($individual_total + $corporate_total, 0).' RWF'" />
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden mb-4">
        <p class="font-semibold text-ink px-5 py-3 border-b border-line">{{ __('body.individual_customers') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.customer') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.bookings_count') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.total_spend') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($individual_rows as $row)
                        <tr>
                            <td class="px-5 py-3.5">{{ $row->customer?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row->bookings) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format((float) $row->spend, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <p class="font-semibold text-ink px-5 py-3 border-b border-line">{{ __('body.corporate_customers') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.company_name') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.bookings_count') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.total_spend') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($corporate_rows as $row)
                        <tr>
                            <td class="px-5 py-3.5">{{ $row->corporateCustomer?->company_name ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format($row->bookings) }}</td>
                            <td class="px-5 py-3.5 text-right">{{ number_format((float) $row->spend, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
