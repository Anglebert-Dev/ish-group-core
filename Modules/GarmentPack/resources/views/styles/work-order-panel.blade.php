<div class="bg-white border border-line rounded-xl p-5 space-y-3">
    <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.wo_panel', ['code' => $style->code]) }}</h2>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
            <th class="px-3 py-2 font-semibold">{{ __('garmentpack::garment.material') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.needed') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.issued') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.remaining') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-line">
            @foreach ($rows as $row)
                <tr>
                    <td class="px-3 py-2.5">{{ $row['material']->variant?->displayName() }}</td>
                    <td class="px-3 py-2.5 text-right">@include('inventory::partials.qty', ['value' => $row['needed']]) {{ $row['material']->variant?->item?->unit }}</td>
                    <td class="px-3 py-2.5 text-right">@include('inventory::partials.qty', ['value' => $row['issued']])</td>
                    <td class="px-3 py-2.5 text-right font-semibold {{ $row['remaining'] > 0 ? 'text-warning' : 'text-success' }}">@include('inventory::partials.qty', ['value' => $row['remaining']])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (collect($rows)->sum('remaining') > 0 && $workOrder->status->isOpen() && \App\Helpers\AuthHelper::can('create production.entry') && \App\Helpers\AuthHelper::can('create inventory.movement'))
        <form action="{{ route('garmentpack.work-orders.issue', $workOrder) }}" method="POST" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="w-56"><x-common.select name="store" :label="__('garmentpack::garment.issue_from')" :options="$stores" hide-placeholder required /></div>
            <x-common.button type="submit" size="sm">{{ __('garmentpack::garment.issue_remaining') }}</x-common.button>
        </form>
    @endif
</div>
