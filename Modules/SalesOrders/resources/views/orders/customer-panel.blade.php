<div class="bg-white border border-line rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-ink">{{ __('salesorders::orders.customer_orders') }}</h2>
        <div class="flex items-center gap-3">
            <a href="{{ route('salesorders.orders.index', ['q' => $customer->displayName()]) }}" class="text-sm font-semibold text-brand">{{ __('inventory::inventory.all_movements') }} →</a>
            @if (\App\Helpers\AuthHelper::can('create sales.order'))
                <x-common.button href="{{ route('salesorders.orders.create', ['customer' => $customer->uuid]) }}" size="sm">+ {{ __('salesorders::orders.customer_new_order') }}</x-common.button>
            @endif
        </div>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-line">
            @forelse ($orders as $order)
                <tr>
                    <td class="px-5 py-3"><a href="{{ route('salesorders.orders.show', $order) }}" class="font-medium text-ink hover:text-brand">{{ $order->number }}</a></td>
                    <td class="px-5 py-3 text-muted">{{ $order->order_date->format('d/m/Y') }}</td>
                    <td class="px-5 py-3 text-right">{{ money($order->total) }}</td>
                    <td class="px-5 py-3"><x-common.badge :color="$order->status->color()">{{ $order->status->label() }}</x-common.badge></td>
                </tr>
            @empty
                <tr><td class="px-5 py-6 text-center text-muted">{{ __('salesorders::orders.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
