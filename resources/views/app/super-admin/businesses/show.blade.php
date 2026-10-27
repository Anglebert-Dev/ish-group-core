@php
    $enabled = $business->enabled_modules;
    $isOwnBusiness = auth('staff')->user()->business_id === $business->id;
@endphp

<x-layouts.super-admin :title="$business->name">
    <x-common.page-header :title="$business->name" :description="$business->owner?->email">
        <x-slot:actions>
            @unless ($isOwnBusiness && $business->is_active)
                <form action="{{ route('superadmin.businesses.active', $business) }}" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="is_active" value="{{ $business->is_active ? 0 : 1 }}">
                    <x-common.button type="submit" :variant="$business->is_active ? 'secondary' : 'primary'" size="sm"
                        data-confirm
                        data-confirm-title="{{ $business->is_active ? __('body.deactivate') : __('body.activate') }} {{ $business->name }}?"
                        data-confirm-message="{{ $business->is_active ? __('body.deactivate_business_warning') : '' }}"
                        data-confirm-variant="{{ $business->is_active ? 'danger' : 'primary' }}"
                    >{{ $business->is_active ? __('body.deactivate') : __('body.activate') }}</x-common.button>
                </form>
            @endunless
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-ink">{{ __('body.users') }}</h2>
                <x-common.status :status="$business->is_active ? 'Active' : 'Inactive'" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                            <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.role') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink whitespace-nowrap">{{ $user->name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $user->email }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $user->roleName() ?? '—' }}</td>
                                <td class="px-5 py-3"><x-common.status :status="ucfirst($user->status->value)" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <form action="{{ route('superadmin.businesses.modules', $business) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf @method('PUT')
                <div>
                    <h2 class="text-base font-semibold text-ink">{{ __('body.business_modules') }}</h2>
                    <p class="text-sm text-muted mt-1">{{ __('body.business_modules_hint') }}</p>
                </div>
                @forelse ($modules as $module)
                    <x-common.checkbox name="modules[]" :value="$module['name']" :label="$module['name']"
                        :checked="$enabled === null || in_array($module['name'], $enabled, true)" />
                @empty
                    <p class="text-sm text-muted">{{ __('body.no_active_modules') }}</p>
                @endforelse
                @if ($modules->isNotEmpty())
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                @endif
            </form>

            @if ($business->owner)
                <form action="{{ route('superadmin.businesses.owner-password', $business) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <h2 class="text-base font-semibold text-ink">{{ __('body.reset_owner_password') }}</h2>
                        <p class="text-sm text-muted mt-1">{{ $business->owner->email }}</p>
                    </div>
                    <x-common.input type="password" name="password" :label="__('body.new_password')" required autocomplete="new-password" />
                    <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" />
                    <x-common.button type="submit" size="sm" variant="secondary">{{ __('body.reset_password') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.super-admin>
