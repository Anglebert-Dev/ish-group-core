<x-layouts.app :title="__('body.super_admin')">
    <x-common.page-header :title="__('body.platform_overview')" :description="__('body.platform_overview_description')" />

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <x-dashboard.stat-card :label="__('body.businesses')" :value="$businesses" :href="route('superadmin.businesses.index')" />
        <x-dashboard.stat-card :label="__('body.active_businesses')" :value="$activeBusinesses" />
        <x-dashboard.stat-card :label="__('body.users')" :value="$users" />
        <x-dashboard.stat-card :label="__('body.active_modules')" :value="$activeModules" :href="route('superadmin.modules.index')" />
    </div>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-line">
            <h2 class="text-base font-semibold text-ink">{{ __('body.recent_businesses') }}</h2>
            <a href="{{ route('superadmin.businesses.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">{{ __('body.view_all') }}</a>
        </div>
        <ul class="divide-y divide-line">
            @forelse ($recentBusinesses as $business)
                <li class="px-5 py-3 flex items-center justify-between gap-4 text-sm">
                    <a href="{{ route('superadmin.businesses.show', $business) }}" class="font-medium text-ink hover:text-brand truncate">{{ $business->name }}</a>
                    <span class="shrink-0 text-muted">{{ $business->owner?->email ?? '—' }}</span>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-muted text-sm">{{ __('body.no_businesses') }}</li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
