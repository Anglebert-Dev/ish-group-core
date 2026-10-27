<x-layouts.app title="{{ $extra->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$extra->name"
            :subtitle="__('body.extra_details')"
            :back-href="route('extras.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($extra->status->value)" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('extras.edit', $extra) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.extra_type')" :value="$extra->type->label()" />
            <x-common.details.summary-item :label="__('body.price')">
                {{ number_format($extra->price, 0) }} RWF
                @if ($extra->isChauffeur())
                    {{ __('body.price_per_day_short') }}
                @endif
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($extra->status->value)" />
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.status-update
            :action="route('extras.status', $extra)"
            :value="$extra->status->value"
            :options="\App\Enums\ExtraStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update bookings.booking_extra')"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M12 6v6m0 0v6m0-6h6m-6 0H6"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.price')">
                    {{ number_format($extra->price, 0) }} RWF
                    @if ($extra->isChauffeur())
                        {{ __('body.price_per_day_short') }}
                    @endif
                </x-common.details.field>
                <x-common.details.field :label="__('body.extra_type')">{{ $extra->type->label() }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="ucfirst($extra->status->value)" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.description')" :span="2">
                    <span class="whitespace-pre-line">{{ $extra->description ?? '—' }}</span>
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
