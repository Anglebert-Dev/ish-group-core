<x-layouts.app title="{{ __('body.compliance_status') }}">
    <x-common.page-header :title="__('body.compliance_status')">
        <x-slot:actions>
            <x-common.button href="{{ route('reports.index') }}" size="sm" variant="secondary">{{ __('body.back') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="flex justify-end mb-4">
        <x-common.export-buttons
            :pdf="route('reports.export.pdf', ['report' => 'compliance-status'])"
            :excel="route('reports.export.excel', ['report' => 'compliance-status'])"
        />
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden mb-4">
        <p class="font-semibold text-ink px-5 py-3 border-b border-line">{{ __('body.drivers') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.driver') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.license_expiry') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($drivers as $row)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $row['driver']->fullName() }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $row['driver']->license_expiry?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3.5"><x-common.badge :color="$row['level']->tone()">{{ $row['summary'] }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <p class="font-semibold text-ink px-5 py-3 border-b border-line">{{ __('body.vehicles') }}</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.owner') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.insurance_expiry') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.inspection_expiry') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($vehicles as $row)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $row['vehicle']->name() }} — {{ $row['vehicle']->plate_number }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $row['vehicle']->owner?->fullName() ?? '—' }}</td>
                            <td class="px-5 py-3.5"><x-common.badge :color="$row['insurance_level']->tone()">{{ $row['insurance_summary'] }}</x-common.badge></td>
                            <td class="px-5 py-3.5"><x-common.badge :color="$row['inspection_level']->tone()">{{ $row['inspection_summary'] }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-muted">{{ __('body.no_report_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
