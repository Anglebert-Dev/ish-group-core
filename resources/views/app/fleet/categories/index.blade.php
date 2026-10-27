<x-layouts.app title="{{ __('body.categories') }}">
    <x-common.page-header :title="__('body.categories')" :description="__('body.categories_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('fleet.categories.export.pdf', request()->query())"
                :excel="route('fleet.categories.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('fleet.categories.create') }}" size="sm">+ {{ __('body.add_category') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.category_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.fleet_daily_rates') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($categories as $category)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('fleet.categories.show', $category) }}" class="hover:text-brand">{{ $category->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $category->fleetDailyPriceLabel() }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($category->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('fleet.categories.show', $category) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('fleet.categories.edit', $category) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('fleet.categories.destroy', $category) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $category->name }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-muted">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$categories" />
    </div>
</x-layouts.app>
