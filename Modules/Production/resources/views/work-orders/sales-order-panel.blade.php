<div class="bg-white border border-line rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-ink">{{ __('production::production.for_order') }}</h2>
        @if ($salesOrder->status->reservesStock() && \App\Helpers\AuthHelper::can('create production.work_order'))
            <x-common.button href="{{ route('production.work-orders.create', ['sales_order' => $salesOrder->uuid]) }}" size="sm">+ {{ __('production::production.for_order_add') }}</x-common.button>
        @endif
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-line">
            @forelse ($workOrders as $wo)
                <tr>
                    <td class="px-5 py-3"><a href="{{ route('production.work-orders.show', $wo) }}" class="font-medium text-ink hover:text-brand">{{ $wo->number }}</a></td>
                    <td class="px-5 py-3">{{ $wo->variant?->displayName() }}</td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">@include('inventory::partials.qty', ['value' => $wo->finished()]) / @include('inventory::partials.qty', ['value' => $wo->quantity])</td>
                    <td class="px-5 py-3"><x-common.badge :color="$wo->status->color()">{{ $wo->status->label() }}</x-common.badge></td>
                </tr>
            @empty
                <tr><td class="px-5 py-6 text-center text-muted">{{ __('production::production.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
