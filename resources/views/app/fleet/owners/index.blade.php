<x-layouts.app title="{{ __('body.vehicle_owners') }}">
    <x-common.page-header :title="__('body.vehicle_owners')" :description="__('body.vehicle_owners_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('fleet.owners.export.pdf', request()->query())"
                :excel="route('fleet.owners.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('fleet.owners.create') }}" size="sm">+ {{ __('body.add_vehicle_owner') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.income_split') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.vehicles') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($owners as $owner)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('fleet.owners.show', $owner) }}" class="hover:text-brand">{{ $owner->fullName() }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $owner->phone }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $owner->splitLabel() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $owner->vehicles_count }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$owner->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('fleet.owners.show', $owner) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('fleet.owners.edit', $owner) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('fleet.owners.destroy', $owner) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $owner->fullName() }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_vehicle_owners_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$owners" />
    </div>
</x-layouts.app>
