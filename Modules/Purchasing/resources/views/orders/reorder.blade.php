<x-layouts.app :title="__('purchasing::purchasing.reorder')">
    <x-common.page-header :title="__('purchasing::purchasing.reorder')" :description="__('purchasing::purchasing.reorder_description')" />

    <form action="{{ route('purchasing.orders.create') }}" method="GET" class="bg-white border border-line rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.item') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.on_hand') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('purchasing::purchasing.on_order') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('purchasing::purchasing.reorder_level') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('purchasing::purchasing.suggested') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-5 py-3 font-medium text-ink">{{ $row['variant']->displayName() }}</td>
                        <td class="px-5 py-3 text-right text-danger font-semibold">@include('inventory::partials.qty', ['value' => $row['on_hand']])</td>
                        <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $row['on_order']])</td>
                        <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $row['reorder']])</td>
                        <td class="px-5 py-3 text-right"><input type="number" step="any" min="0" name="variants[{{ $row['variant']->uuid }}]" value="{{ $row['suggest'] }}" class="w-24 rounded-lg border border-line px-2 py-1.5 text-right text-sm"></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('purchasing::purchasing.reorder_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($rows->isNotEmpty() && \App\Helpers\AuthHelper::can('create purchasing.order'))
            <div class="px-5 py-4 border-t border-line"><x-common.button type="submit" size="sm">{{ __('purchasing::purchasing.reorder_create') }}</x-common.button></div>
        @endif
    </form>
</x-layouts.app>
