<x-layouts.app title="{{ __('body.vehicles') }}">
    <x-common.page-header :title="__('body.vehicles')" :description="__('body.vehicles_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('fleet.vehicles.export.pdf', request()->query())"
                :excel="route('fleet.vehicles.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('fleet.vehicles.create') }}" size="sm">+ {{ __('body.add_vehicle') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.plate_number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.category') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.daily_rate') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.vehicle_owner') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.transmission') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.compliance') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($vehicles as $vehicle)
                    @php
                        $worstCompliance = collect([
                            $vehicle->insuranceAlertLevel(),
                            $vehicle->inspectionAlertLevel(),
                        ])->sortBy(fn ($level) => match ($level) {
                            \App\Enums\DocumentAlertLevel::Red => 0,
                            \App\Enums\DocumentAlertLevel::Yellow => 1,
                            \App\Enums\DocumentAlertLevel::Unknown => 2,
                            default => 3,
                        })->first();
                    @endphp
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('fleet.vehicles.show', $vehicle) }}" class="hover:text-brand">{{ $vehicle->name() }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $vehicle->plate_number }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $vehicle->category?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ number_format((float) ($vehicle->dailyPrice() ?? 0), 0) }} RWF/day</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if ($vehicle->owner)
                                <span class="block">{{ $vehicle->owner->fullName() }}</span>
                                <span class="text-xs text-muted">{{ __('body.outsider_owner') }}</span>
                            @else
                                {{ __('body.company_fleet') }}
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $vehicle->transmission->label() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if ($worstCompliance->isAttention() || $worstCompliance === \App\Enums\DocumentAlertLevel::Unknown)
                                <x-common.badge :color="$worstCompliance->tone()">{{ $worstCompliance->label() }}</x-common.badge>
                            @else
                                <x-common.badge color="success">{{ __('body.document_alert_ok') }}</x-common.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5"><x-common.status :status="$vehicle->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('fleet.vehicles.show', $vehicle) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('fleet.vehicles.edit', $vehicle) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('fleet.vehicles.destroy', $vehicle) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $vehicle->name() }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-10 text-center text-muted">No vehicles yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$vehicles" />
    </div>
</x-layouts.app>
