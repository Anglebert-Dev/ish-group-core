<x-layouts.app :title="__('body.super_admin')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.platform_overview')" :description="__('body.platform_overview_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.businesses.create') }}" size="sm">+ {{ __('body.add_business') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <x-dashboard.stat-card :label="__('body.businesses')" :value="$businesses" :href="route('superadmin.businesses.index')" />
        <x-dashboard.stat-card :label="__('body.active_businesses')" :value="$activeBusinesses" :href="route('superadmin.businesses.index', ['status' => 'active'])" />
        <x-dashboard.stat-card :label="__('body.users')" :value="$users" />
        <x-dashboard.stat-card :label="__('body.active_modules')" :value="$activeModules" :href="route('superadmin.modules.index')" />
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
            <h2 class="font-display font-bold text-ink">{{ __('body.recent_businesses') }}</h2>
            <a href="{{ route('superadmin.businesses.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('body.business_name') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.owner') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.created') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($recentBusinesses as $business)
                        <tr class="hover:bg-surface">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $business->name }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">{{ $business->owner?->email ?? '—' }}</td>
                            <td class="px-5 py-3.5"><x-common.status :status="$business->is_active ? 'Active' : 'Inactive'" /></td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime($business->created_at)</td>
                            <td class="px-5 py-3.5"><x-common.action-button :href="route('superadmin.businesses.show', $business)">{{ __('body.manage') }}</x-common.action-button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_businesses') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
