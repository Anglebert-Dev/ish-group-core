@php
    $can = fn (string $permission) => \App\Helpers\AuthHelper::can($permission);
    $status = $order->status;
    $closed = in_array($status, [\Modules\SalesOrders\Enums\OrderStatus::Delivered, \Modules\SalesOrders\Enums\OrderStatus::Cancelled], true);
@endphp
<x-layouts.app :title="$order->number">
    <x-common.page-header :title="$order->number" :description="$order->customer?->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ route('salesorders.orders.index') }}" variant="secondary" size="sm">← {{ __('salesorders::orders.orders') }}</x-common.button>
            @if (! $closed && $can('update sales.order'))
                <x-common.button href="{{ route('salesorders.orders.edit', $order) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.info-card :title="__('salesorders::orders.order')" :rows="[
                    [__('salesorders::orders.order_date'), $order->order_date->format('d/m/Y')],
                    [__('salesorders::orders.due_date'), $order->due_date?->format('d/m/Y')],
                    [__('salesorders::orders.store'), $order->store?->name],
                    [__('salesorders::orders.customer_reference'), $order->customer_reference],
                ]" />
                <x-common.info-card :title="__('salesorders::orders.customer')" :rows="[
                    [__('body.name'), $order->customer?->displayName()],
                    [__('body.phone'), $order->customer?->phone],
                    [__('body.email_address'), $order->customer?->email],
                    [__('body.customer_delivery_address'), $order->customer?->delivery_address ?: $order->customer?->address],
                ]" />
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('salesorders::orders.lines') }}</h2>
                @include('salesorders::orders.lines-table', ['order' => $order, 'available' => $closed ? null : $available])
            </div>

            @if ($order->notes)
                <div class="bg-white border border-line rounded-xl p-5">
                    <h2 class="font-display font-bold text-ink mb-2">{{ __('salesorders::orders.notes') }}</h2>
                    <p class="text-sm text-body leading-relaxed">{!! nl2br(e($order->notes)) !!}</p>
                </div>
            @endif

            {{-- Modules add panels here (work orders, invoices, deliveries…) --}}
            @foreach ($panels as $panel)
                @if (filled($panel)) {!! $panel !!} @endif
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <div class="flex gap-1.5">
                        <x-common.badge :color="$status->color()">{{ $status->label() }}</x-common.badge>
                        @if ($order->isOverdue())<x-common.badge color="danger">{{ __('salesorders::orders.overdue') }}</x-common.badge>@endif
                    </div>
                </div>

                @if ($status === \Modules\SalesOrders\Enums\OrderStatus::Pending && $can('approve sales.order'))
                    <form action="{{ route('salesorders.orders.confirm', $order) }}" method="POST" class="space-y-1">
                        @csrf
                        <x-common.button type="submit" class="w-full">{{ __('salesorders::orders.action_confirm') }}</x-common.button>
                        <p class="text-xs text-muted">{{ __('salesorders::orders.action_confirm_hint') }}</p>
                    </form>
                @endif

                @if ($status->reservesStock())
                    @if ($can('update sales.order'))
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-muted">{{ __('salesorders::orders.move_to') }}</span>
                            @foreach (\Modules\SalesOrders\Enums\OrderStatus::progress() as $next)
                                @continue($next === $status)
                                <x-common.action-button :action="route('salesorders.orders.progress', $order)" method="PUT" color="muted">
                                    <x-slot:fields><input type="hidden" name="status" value="{{ $next->value }}"></x-slot:fields>
                                    {{ $next->label() }}
                                </x-common.action-button>
                            @endforeach
                        </div>
                    @endif
                    @if ($can('approve sales.order'))
                        <form action="{{ route('salesorders.orders.deliver', $order) }}" method="POST" class="space-y-1">
                            @csrf
                            <x-common.button type="submit" class="w-full" data-confirm data-confirm-title="{{ __('salesorders::orders.action_deliver') }}?">{{ __('salesorders::orders.action_deliver') }}</x-common.button>
                            <p class="text-xs text-muted">{{ __('salesorders::orders.action_deliver_hint', ['store' => $order->store?->name]) }}</p>
                        </form>
                    @endif
                @endif

                @if ($status === \Modules\SalesOrders\Enums\OrderStatus::Delivered)
                    <p class="text-sm text-muted">@datetime($order->delivered_at)</p>
                    @if ($movements && $can('list inventory.movement'))
                        <a href="{{ route('inventory.movements.index', ['type' => 'sale']) }}" class="text-sm font-semibold text-brand">{{ __('salesorders::orders.stock_moved') }} ({{ $movements }}) →</a>
                    @endif
                @endif

                @if ($status === \Modules\SalesOrders\Enums\OrderStatus::Cancelled)
                    <p class="text-sm text-muted">{{ __('salesorders::orders.cancelled_because', ['reason' => $order->cancel_reason]) }}</p>
                @endif
            </div>

            @if (! $closed && $can('delete sales.order'))
                <form action="{{ route('salesorders.orders.cancel', $order) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('salesorders::orders.action_cancel') }}</h2>
                    <x-common.input name="reason" :label="__('salesorders::orders.cancel_reason')" required />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('salesorders::orders.action_cancel') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
