<x-layouts.app title="{{ $notification->title }}">
    <div data-details-page>
        <x-common.details.header
            :title="$notification->title"
            :subtitle="$notification->type->label()"
            :back-href="route('notifications.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="$notification->status->label()" />
            </x-slot:meta>
            <x-slot:actions>
                @if ($notification->action_url)
                    <x-common.button href="{{ $notification->action_url }}" size="sm">{{ __('body.open_related') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="$notification->status->label()" />
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.when')">
                @kigali($notification->created_at)
            </x-common.details.summary-item>
        </x-common.details.summary>

        <x-common.details.section
            :title="__('body.overview')"
            icon="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="$notification->status->label()" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.when')">
                    @kigali($notification->created_at)
                </x-common.details.field>
                <x-common.details.field :label="__('body.details')" :span="2">
                    {{ $notification->body ?: '—' }}
                </x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
