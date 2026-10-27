<x-layouts.app title="{{ $category->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$category->name"
            :subtitle="__('body.category_details')"
            :back-href="route('fleet.categories.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($category->status->value)" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('fleet.categories.edit', $category) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.fleet_daily_rates')" :value="$category->fleetDailyPriceLabel()" />
            <x-common.details.summary-item :label="__('body.category_suggested_daily_price')" :value="number_format((float) $category->daily_price, 0).' RWF/'.__('body.day')" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($category->status->value)" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('fleet.categories.status', $category)"
            :value="$category->status->value"
            :options="\App\Enums\VehicleCategoryStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update fleet.vehicle_category')"
        />


        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <x-common.details.section
                :title="__('body.overview')"
                icon="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
            >
                <x-common.details.grid>
                    <x-common.details.field :label="__('body.fleet_daily_rates')">{{ $category->fleetDailyPriceLabel() }}</x-common.details.field>
                    <x-common.details.field :label="__('body.category_suggested_daily_price')">{{ number_format((float) $category->daily_price, 0) }} RWF/{{ __('body.day') }}</x-common.details.field>
                    <x-common.details.field :label="__('body.status')">
                        <x-common.status :status="ucfirst($category->status->value)" />
                    </x-common.details.field>
                    <x-common.details.field :label="__('body.description')" :span="2">
                        <span class="whitespace-pre-line">{{ $category->description ?? '—' }}</span>
                    </x-common.details.field>
                </x-common.details.grid>
            </x-common.details.section>

            <x-common.details.section
                :title="__('body.category_vehicles_in_group')"
                icon="M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3"
            >
                @if ($category->vehicles->isEmpty())
                    <p class="text-sm text-muted">{{ __('body.no_vehicles_yet') }}</p>
                @else
                    <ul class="divide-y divide-line rounded-xl border border-line overflow-hidden">
                        @foreach ($category->vehicles as $vehicle)
                            <li class="flex items-center justify-between gap-3 px-4 py-3 bg-white text-sm">
                                <a href="{{ route('fleet.vehicles.show', $vehicle) }}" class="font-medium text-ink hover:text-brand">{{ $vehicle->name() }} — {{ $vehicle->plate_number }}</a>
                                <span class="text-muted tabular-nums shrink-0">{{ number_format((float) ($vehicle->dailyPrice() ?? 0), 0) }} RWF/{{ __('body.day') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-common.details.section>

            <x-common.details.section
                :title="__('body.image')"
                icon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                tone="ink"
            >
                @if ($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="w-full h-auto rounded-xl object-cover border border-line">
                @else
                    <p class="text-sm text-muted">{{ __('body.no_images_yet') }}</p>
                @endif
            </x-common.details.section>
        </div>
    </div>
</x-layouts.app>
