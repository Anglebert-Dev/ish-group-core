<x-layouts.app title="{{ $maintenance->vehicle?->name() ?? __('body.maintenance') }}">
    @php
        $canUpdate = \App\Helpers\AuthHelper::can('update fleet.vehicle_maintenance');
    @endphp

    <div data-details-page>
        <x-common.details.header
            :title="$maintenance->vehicle?->name() ?? __('body.maintenance')"
            :subtitle="__('body.maintenance_details')"
            :back-href="route('fleet.maintenance.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$maintenance->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($canUpdate)
                    <x-common.button href="{{ route('fleet.maintenance.edit', $maintenance) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.maintenance_type')" :value="$maintenance->maintenance_type->label()" />
            <x-common.details.summary-item :label="__('body.date')" :value="$maintenance->date->format('d M Y')" />
            <x-common.details.summary-item :label="__('body.cost')" :value="$maintenance->cost !== null ? number_format($maintenance->cost, 2).' RWF' : '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$maintenance->status->label()" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        @if ($canUpdate)
            <x-common.status-update
                :action="route('fleet.maintenance.status', $maintenance)"
                :value="$maintenance->status->value"
                :options="\App\Enums\MaintenanceStatus::cases()"
                :can="true"
            />
        @endif

        <x-common.details.section
            :title="__('body.overview')"
            icon="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.maintenance_type')">{{ $maintenance->maintenance_type->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$maintenance->status->label()" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.date')">{{ $maintenance->date->format('d M Y') }}</x-common.details.field>
                <x-common.details.field :label="__('body.cost')">{{ $maintenance->cost !== null ? number_format($maintenance->cost, 2).' RWF' : '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.mileage')">{{ $maintenance->mileage !== null ? number_format($maintenance->mileage).' km' : '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.description')" :span="2">
                    <span class="whitespace-pre-line">{{ $maintenance->description ?? '—' }}</span>
                </x-common.details.field>
                <x-common.details.field :label="__('body.notes')" :span="2">
                    <span class="whitespace-pre-line">{{ $maintenance->notes ?? '—' }}</span>
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
