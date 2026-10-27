<x-layouts.super-admin :title="__('body.businesses')">
    <x-common.page-header :title="__('body.businesses')" :description="__('body.businesses_description')">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.businesses.create') }}" size="sm">+ {{ __('body.add_business') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.business_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.owner') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.users') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($businesses as $business)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('superadmin.businesses.show', $business) }}" class="hover:text-brand">{{ $business->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $business->owner?->email ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $business->users_count }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$business->is_active ? 'Active' : 'Inactive'" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('superadmin.businesses.show', $business) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('body.no_businesses') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$businesses" class="border-t border-line" />
    </div>
</x-layouts.super-admin>
