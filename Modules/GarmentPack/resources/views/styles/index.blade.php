<x-layouts.app :title="__('garmentpack::garment.styles')">
    <x-common.page-header :title="__('garmentpack::garment.styles')" :description="__('garmentpack::garment.styles_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create garments.style'))
                <x-common.button href="{{ route('garmentpack.styles.create') }}" size="sm">+ {{ __('garmentpack::garment.add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('garmentpack.styles.index')" :open="filled($q)">
        <x-common.input name="q" :label="__('body.search')" :value="$q" compact />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.code') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('garmentpack::garment.product') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($styles as $style)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5"><a href="{{ route('garmentpack.styles.show', $style) }}" class="font-medium text-ink hover:text-brand">{{ $style->code }}</a></td>
                        <td class="px-5 py-3.5">{{ $style->name }}</td>
                        <td class="px-5 py-3.5">{{ $style->item?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-muted">{{ __('garmentpack::garment.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-common.pagination :paginator="$styles" />
    </div>
</x-layouts.app>
