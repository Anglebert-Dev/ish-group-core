<x-layouts.app :title="__('distribution::distribution.sites')">
    @include('distribution::partials.nav')

    <x-common.page-header :title="__('distribution::distribution.sites')" :description="__('distribution::distribution.sites_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.distribution.sites.create') }}" size="sm">+ {{ __('distribution::distribution.add_site') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    @if ($sites->isEmpty())
        <x-common.empty-state :title="__('distribution::distribution.no_sites')" :description="__('distribution::distribution.no_sites_hint')" />
    @else
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.site_name') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.customer_name') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.modules') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.update_mode') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.last_check_in') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($sites as $site)
                            <tr class="hover:bg-surface">
                                <td class="px-5 py-3.5 font-medium text-ink">
                                    {{ $site->name }}
                                    @if ($site->app_url)<div class="text-xs text-muted">{{ $site->app_url }}</div>@endif
                                </td>
                                <td class="px-5 py-3.5">{{ $site->customer_name ?: '—' }}</td>
                                <td class="px-5 py-3.5">{{ $site->modules_count }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap">{{ $site->update_mode->label() }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $site->last_check_in_at?->diffForHumans() ?? __('distribution::distribution.never') }}</td>
                                <td class="px-5 py-3.5"><x-common.badge :color="$site->status->color()">{{ $site->status->label() }}</x-common.badge></td>
                                <td class="px-5 py-3.5"><x-common.action-button :href="route('superadmin.distribution.sites.show', $site)">{{ __('body.manage') }}</x-common.action-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4"><x-common.pagination :paginator="$sites" /></div>
    @endif
</x-layouts.app>
