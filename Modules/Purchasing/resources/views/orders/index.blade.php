<x-layouts.app :title="__('purchasing::purchasing.orders')">
    <x-common.page-header :title="__('purchasing::purchasing.orders')" :description="__('purchasing::purchasing.orders_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create purchasing.order'))
                <x-common.button href="{{ route('purchasing.orders.create') }}" size="sm">+ {{ __('purchasing::purchasing.order_add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('purchasing.orders.index')" :open="filled($status)">
        <x-common.select name="status" :label="__('body.status')" :value="$status ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + collect(\Modules\Purchasing\Enums\PurchaseStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('purchasing::purchasing.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('purchasing::purchasing.supplier') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('purchasing::purchasing.order_date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('purchasing::purchasing.expected_date') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.total') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($orders as $order)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5"><a href="{{ route('purchasing.orders.show', $order) }}" class="font-medium text-ink hover:text-brand">{{ $order->number }}</a></td>
                        <td class="px-5 py-3.5">{{ $order->supplier?->name }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $order->order_date->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $order->expected_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-right">{{ money($order->total) }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$order->status->color()">{{ $order->status->label() }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('purchasing::purchasing.orders_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-common.pagination :paginator="$orders" />
    </div>
</x-layouts.app>
