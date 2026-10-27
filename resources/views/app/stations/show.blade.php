<x-layouts.app title="{{ $station->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$station->name"
            :subtitle="__('body.station_details')"
            :back-href="route('stations.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($station->status->value)" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('stations.edit', $station) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.code')" :value="$station->code" />
            <x-common.details.summary-item :label="__('body.city')" :value="$station->city ?? '—'" />
            <x-common.details.summary-item :label="__('body.phone')" :value="$station->phone ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($station->status->value)" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('stations.status', $station)"
            :value="$station->status->value"
            :options="\App\Enums\StationStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update stations.station')"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.code')">{{ $station->code }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="ucfirst($station->status->value)" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.phone')">{{ $station->phone ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.email_address')">{{ $station->email ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.city')">{{ $station->city ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.opening_time').' / '.__('body.closing_time')">
                    {{ $station->opening_time ?? '—' }} – {{ $station->closing_time ?? '—' }}
                </x-common.details.field>
                <x-common.details.field :label="__('body.address')" :span="2">
                    <span class="whitespace-pre-line">{{ $station->address ?? '—' }}</span>
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
