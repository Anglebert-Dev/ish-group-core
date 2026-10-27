@php
    $canUpdate = \App\Helpers\AuthHelper::can('update fleet.vehicle_owner');
    $tabs = [
        ['id' => 'overview', 'label' => __('body.identity'), 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['id' => 'agreement', 'label' => __('body.income_agreement'), 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['id' => 'vehicles', 'label' => __('body.vehicles'), 'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3', 'badge' => $owner->vehicles->count() ?: null],
    ];
@endphp
<x-layouts.app title="{{ $owner->fullName() }}">
    <div data-details-page>
        <x-common.details.header
            :title="$owner->fullName()"
            :subtitle="__('body.vehicle_owner_details')"
            :back-href="route('fleet.owners.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$owner->status->label()" />
                <span class="text-xs px-2 py-1 rounded font-semibold bg-brand-light text-brand-dark">{{ $owner->splitLabel() }}</span>
            </x-slot:meta>
            <x-slot:actions>
                @if ($canUpdate)
                    <x-common.button href="{{ route('fleet.owners.edit', $owner) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.phone')" :value="$owner->phone" />
            <x-common.details.summary-item :label="__('body.income_split')" :value="$owner->splitLabel()" />
            <x-common.details.summary-item :label="__('body.vehicles')" :value="(string) $owner->vehicles->count()" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$owner->status->label()" />
            </x-common.details.summary-item>
            <x-slot:tabs>
                <x-common.details.tabs :tabs="$tabs" active="overview" />
            </x-slot:tabs>
        </x-common.details.summary>

        @if ($canUpdate)
            <x-common.status-update
                :action="route('fleet.owners.status', $owner)"
                :value="$owner->status->value"
                :options="\App\Enums\VehicleOwnerStatus::cases()"
                :can="true"
            />
        @endif

        <div data-details-panel="overview" class="space-y-6">
            <x-common.details.section :title="__('body.identity')" icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.phone')">{{ $owner->phone }}</x-common.details.field>
                    <x-common.details.field :label="__('body.email_address')">{{ $owner->email ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.national_id')">{{ $owner->national_id ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="$owner->status->label()" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.address')" :span="3">
                        <span class="whitespace-pre-line">{{ $owner->address ?? '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="agreement" class="hidden space-y-6">
            <x-common.details.section :title="__('body.income_agreement')" icon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" tone="success">
                <x-common.details.grid :cols="3">
                    <x-common.details.field :label="__('body.owner_share_percent')">{{ number_format((float) $owner->owner_share_percent, 2) }}%</x-common.details.field>
                    <x-common.details.field :label="__('body.company_share_percent')">{{ number_format((float) $owner->company_share_percent, 2) }}%</x-common.details.field>
                    <x-common.details.field :label="__('body.income_split')">{{ $owner->splitLabel() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.agreement_starts_on')">{{ $owner->agreement_starts_on?->format('d M Y') ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.agreement_ends_on')">{{ $owner->agreement_ends_on?->format('d M Y') ?? '—' }}</x-common.details.field>
                    <x-common.details.field :label="__('body.notes')" :span="3">
                        <span class="whitespace-pre-line">{{ $owner->notes ?? '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>
        </div>

        <div data-details-panel="vehicles" class="hidden space-y-6">
            <x-common.details.section :title="__('body.vehicles')" icon="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3" tone="ink">
                <div class="overflow-x-auto -mx-1">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                                <th class="px-3 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ __('body.plate_number') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ __('body.category') }}</th>
                                <th class="px-3 py-3 font-semibold">{{ __('body.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($owner->vehicles as $vehicle)
                                <tr class="hover:bg-surface">
                                    <td class="px-3 py-3.5 font-medium text-ink whitespace-nowrap">
                                        <a href="{{ route('fleet.vehicles.show', $vehicle) }}" class="hover:text-brand">{{ $vehicle->name() }}</a>
                                    </td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">{{ $vehicle->plate_number }}</td>
                                    <td class="px-3 py-3.5 whitespace-nowrap">{{ $vehicle->category?->name ?? '—' }}</td>
                                    <td class="px-3 py-3.5"><x-common.status :status="$vehicle->status->label()" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-10 text-center text-muted">{{ __('body.no_vehicles_assigned_to_owner') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
