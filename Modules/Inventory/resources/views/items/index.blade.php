@php
    $type = $filters['type'] ?? '';
    $tabs = ['' => __('inventory::inventory.tab_all'), 'finished' => __('inventory::inventory.tab_finished'), 'material' => __('inventory::inventory.tab_material')];
@endphp
<x-layouts.app :title="__('inventory::inventory.items')">
    <x-common.page-header :title="__('inventory::inventory.items')" :description="__('inventory::inventory.items_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('inventory.items.export.pdf', request()->query())"
                :excel="route('inventory.items.export.excel', request()->query())"
            />
            @if (\App\Helpers\AuthHelper::can('create inventory.item'))
                <x-common.button href="{{ route('inventory.items.create', $type ? ['type' => $type] : []) }}" size="sm">+ {{ __('inventory::inventory.item_add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach ($tabs as $value => $label)
            <a href="{{ route('inventory.items.index', array_filter(['type' => $value] + $filters)) }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold border transition-colors {{ $type === $value ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line hover:border-brand hover:text-brand' }}">{{ $label }}</a>
        @endforeach
    </div>

    <x-common.filter-panel :action="route('inventory.items.index')" :open="array_filter(\Illuminate\Support\Arr::except($filters, 'type')) !== []">
        <input type="hidden" name="type" value="{{ $type }}">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('inventory::inventory.item_search_placeholder')" compact />
        <x-common.select name="category" :label="__('inventory::inventory.category')" :value="$filters['category'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + array_combine($categories, $categories)" />
        <x-common.select name="stock" :label="__('inventory::inventory.on_hand')" :value="$filters['stock'] ?? ''" compact hide-placeholder
            :options="['' => __('inventory::inventory.stock_all'), 'low' => __('inventory::inventory.stock_low'), 'out' => __('inventory::inventory.stock_out')]" />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? 'active'" compact hide-placeholder
            :options="['active' => __('inventory::inventory.status_active'), 'inactive' => __('inventory::inventory.status_inactive'), 'all' => __('body.all')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.item') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.category') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.variants') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.on_hand') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($items as $item)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('inventory.items.show', $item) }}" class="font-medium text-ink hover:text-brand">{{ $item->name }}</a>
                            <p class="text-xs text-muted">{{ $item->type->label() }}{{ $item->sku ? ' · '.$item->sku : '' }}</p>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $item->category ?: '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $item->hasOptions() ? $item->variants_count : '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right font-semibold text-ink">@include('inventory::partials.qty', ['value' => $item->on_hand]) <span class="font-normal text-muted">{{ $item->unit }}</span></td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if (! $item->is_active)
                                <x-common.badge color="muted">{{ __('inventory::inventory.status_inactive') }}</x-common.badge>
                            @elseif ((float) $item->on_hand <= 0)
                                <x-common.badge color="danger">{{ __('inventory::inventory.out_of_stock') }}</x-common.badge>
                            @elseif ($item->low_variants > 0)
                                <x-common.badge color="warning">{{ __('inventory::inventory.low_stock') }}</x-common.badge>
                            @else
                                <x-common.badge color="success">{{ __('inventory::inventory.status_active') }}</x-common.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.action-button :href="route('inventory.items.show', $item)">{{ __('body.manage') }}</x-common.action-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('inventory::inventory.item_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$items" />
    </div>
</x-layouts.app>
