<x-layouts.app :title="__('body.businesses')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.all_businesses')" :description="__('body.businesses_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('superadmin.businesses.export.pdf', request()->query())"
                :excel="route('superadmin.businesses.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('superadmin.businesses.create') }}" size="sm">+ {{ __('body.add_business') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('superadmin.businesses.index')" :open="filled($filters['q'] ?? null) || filled($filters['status'] ?? null)">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('body.search_businesses_placeholder')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact
            :options="['' => __('body.all'), 'active' => __('body.active'), 'inactive' => __('body.inactive')]" hide-placeholder />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.business_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.owner') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.users') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.created') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($businesses as $business)
                    @php $isOwn = auth('staff')->user()->business_id === $business->id; @endphp
                    <tr class="hover:bg-surface align-top">
                        <td class="px-5 py-4 font-medium text-ink">
                            <a href="{{ route('superadmin.businesses.show', $business) }}" class="hover:text-brand">{{ $business->name }}</a>
                            @if ($isOwn)
                                <span class="ml-1 text-xs text-muted">({{ __('body.yours') }})</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $business->owner?->name ?? '—' }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $business->owner?->email ?? '—' }}</td>
                        <td class="px-5 py-4">{{ $business->users_count }}</td>
                        <td class="px-5 py-4"><x-common.status :status="$business->is_active ? 'Active' : 'Inactive'" /></td>
                        <td class="px-5 py-4 text-muted whitespace-nowrap">@datetime($business->created_at)</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-col items-start gap-1.5">
                                <x-common.action-button :href="route('superadmin.businesses.show', $business)" color="brand">{{ __('body.manage') }}</x-common.action-button>
                                @unless ($isOwn)
                                    <x-common.action-button :action="route('superadmin.businesses.active', $business)" method="PUT"
                                        :color="$business->is_active ? 'warning' : 'success'"
                                        data-confirm
                                        data-confirm-title="{{ ($business->is_active ? __('body.deactivate') : __('body.activate')).' '.$business->name }}?"
                                        data-confirm-message="{{ $business->is_active ? __('body.deactivate_business_warning') : '' }}"
                                        data-confirm-variant="{{ $business->is_active ? 'danger' : 'primary' }}">
                                        <x-slot:fields><input type="hidden" name="is_active" value="{{ $business->is_active ? 0 : 1 }}"></x-slot:fields>
                                        {{ $business->is_active ? __('body.deactivate') : __('body.activate') }}
                                    </x-common.action-button>
                                    <x-common.action-button :href="route('superadmin.businesses.show', $business).'#delete-business'" color="danger">{{ __('body.delete') }}</x-common.action-button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('body.no_businesses') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$businesses" class="border-t border-line" />
    </div>
</x-layouts.app>
