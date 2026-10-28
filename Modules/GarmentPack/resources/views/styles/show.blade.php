@php $missingCost = collect($costing['lines'])->contains(fn ($l) => $l['material']->variant?->cost_price === null); @endphp
<x-layouts.app :title="$style->code">
    <x-common.page-header :title="$style->code.' · '.$style->name" :description="$style->item?->name">
        <x-slot:actions>
            <x-common.button href="{{ route('garmentpack.styles.index') }}" variant="secondary" size="sm">← {{ __('garmentpack::garment.styles') }}</x-common.button>
            @if (\App\Helpers\AuthHelper::can('update garments.style'))
                <x-common.button href="{{ route('garmentpack.styles.edit', $style) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.bom') }}</h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-3 py-2.5 font-semibold">{{ __('garmentpack::garment.material') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('garmentpack::garment.per_piece') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('garmentpack::garment.waste') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('garmentpack::garment.cost_materials') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($costing['lines'] as $line)
                            <tr>
                                <td class="px-3 py-3"><span class="font-medium text-ink">{{ $line['material']->variant?->displayName() }}</span>@if ($line['material']->note)<span class="block text-xs text-muted">{{ $line['material']->note }}</span>@endif</td>
                                <td class="px-3 py-3 text-right">{{ rtrim(rtrim(number_format($line['material']->quantity, 4), '0'), '.') }} {{ $line['material']->variant?->item?->unit }}</td>
                                <td class="px-3 py-3 text-right">{{ rtrim(rtrim(number_format($line['material']->waste_percent, 2), '0'), '.') }}%</td>
                                <td class="px-3 py-3 text-right">{{ money($line['cost']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-6 text-center text-muted">{{ __('garmentpack::garment.bom_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($missingCost)<p class="text-xs text-warning">{{ __('garmentpack::garment.no_cost_price') }}</p>@endif
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.margin') }}</h2>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.variant') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('garmentpack::garment.margin_price') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('garmentpack::garment.margin_value') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($style->item?->variants->where('is_active', true) ?? [] as $variant)
                            @php $price = (float) $variant->sale_price; $margin = $price - $costing['total']; @endphp
                            <tr>
                                <td class="px-3 py-3">{{ $variant->optionLabel() ?: $style->item->name }}</td>
                                <td class="px-3 py-3 text-right">{{ $variant->sale_price !== null ? money($price) : '—' }}</td>
                                <td class="px-3 py-3 text-right font-semibold {{ $margin < 0 ? 'text-danger' : 'text-success' }}">
                                    @if ($variant->sale_price !== null){{ money($margin) }} @if ($price > 0)<span class="text-xs text-muted">({{ round($margin / $price * 100) }}%)</span>@endif @else — @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 space-y-2">
            <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.costing') }}</h2>
            @foreach (['materials' => 'cost_materials', 'labour' => 'cost_labour', 'overhead' => 'cost_overhead'] as $key => $label)
                <div class="flex justify-between text-sm"><span class="text-muted">{{ __('garmentpack::garment.'.$label) }}</span><span>{{ money($costing[$key]) }}</span></div>
            @endforeach
            <div class="flex justify-between text-base font-bold border-t border-line pt-2"><span>{{ __('garmentpack::garment.cost_total') }}</span><span>{{ money($costing['total']) }}</span></div>
            @if ($style->description)<p class="text-sm text-muted pt-3">{!! nl2br(e($style->description)) !!}</p>@endif
        </div>
    </div>
</x-layouts.app>
