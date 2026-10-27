<x-layouts.app :title="__('salesorders::orders.orders')">
    <x-common.page-header :title="__('salesorders::orders.orders')" :description="__('salesorders::orders.orders_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('salesorders.orders.export.pdf', request()->query())"
                :excel="route('salesorders.orders.export.excel', request()->query())"
            />
            @if (\App\Helpers\AuthHelper::can('create sales.order'))
                <x-common.button href="{{ route('salesorders.orders.create') }}" size="sm">+ {{ __('salesorders::orders.add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('salesorders.orders.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('salesorders::orders.search_placeholder')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + collect(\Modules\SalesOrders\Enums\OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-common.input name="from" type="date" :label="__('body.audit_from')" :value="$filters['from'] ?? ''" compact />
        <x-common.input name="to" type="date" :label="__('body.audit_to')" :value="$filters['to'] ?? ''" compact />
        <x-common.select name="overdue" :label="__('salesorders::orders.overdue')" :value="$filters['overdue'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), '1' => __('salesorders::orders.overdue_only')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('salesorders::orders.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('salesorders::orders.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('salesorders::orders.order_date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('salesorders::orders.due_date') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.total') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($orders as $order)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 whitespace-nowrap"><a href="{{ route('salesorders.orders.show', $order) }}" class="font-medium text-ink hover:text-brand">{{ $order->number }}</a></td>
                        <td class="px-5 py-3.5">{{ $order->customer?->displayName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $order->order_date->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap {{ $order->isOverdue() ? 'text-danger font-semibold' : '' }}">{{ $order->due_date ? $order->due_date->format('d/m/Y') : '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-right font-semibold text-ink">{{ money($order->total) }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.badge :color="$order->status->color()">{{ $order->status->label() }}</x-common.badge>
                            @if ($order->isOverdue())<x-common.badge color="danger">{{ __('salesorders::orders.overdue') }}</x-common.badge>@endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap"><x-common.action-button :href="route('salesorders.orders.show', $order)">{{ __('body.manage') }}</x-common.action-button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-muted">{{ __('salesorders::orders.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$orders" />
    </div>
</x-layouts.app>
