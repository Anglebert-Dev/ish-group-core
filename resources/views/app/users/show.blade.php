<x-layouts.app title="{{ $user->name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$user->name"
            :subtitle="__('body.staff_description')"
            :back-href="route('users.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <x-common.status :status="ucfirst($user->status->value)" />
            </x-slot:meta>
            <x-slot:actions>
                <x-common.button href="{{ route('users.edit', $user) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.email_address')" :value="$user->email" />
            <x-common.details.summary-item :label="__('body.role')" :value="$user->roles->first()?->name ?? '—'" />
            <x-common.details.summary-item :label="__('body.status')">
                <x-common.status :status="ucfirst($user->status->value)" />
            </x-common.details.summary-item>
            <x-common.details.summary-item :label="__('body.joined')" :value="$user->created_at->format('d M Y')" />
        </x-common.details.summary>

        <x-common.status-update
            :action="route('users.status', $user)"
            :value="$user->status->value"
            :options="\App\Enums\UserStatus::cases()"
            :can="\App\Helpers\AuthHelper::can('update users.user') && ! auth('staff')->user()->is($user)"
        />


        <x-common.details.section
            :title="__('body.overview')"
            icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
        >
            <x-common.details.grid>
                <x-common.details.field :label="__('body.full_name')">{{ $user->name }}</x-common.details.field>
                <x-common.details.field :label="__('body.email_address')">{{ $user->email }}</x-common.details.field>
                <x-common.details.field :label="__('body.role')">{{ $user->roles->first()?->name ?? '—' }}</x-common.details.field>
                <x-common.details.field :label="__('body.status')">
                    <x-common.status :status="ucfirst($user->status->value)" />
                </x-common.details.field>
                <x-common.details.field :label="__('body.joined')">{{ $user->created_at->format('d M Y') }}</x-common.details.field>
            </x-common.details.grid>
        </x-common.details.section>
    </div>
</x-layouts.app>
