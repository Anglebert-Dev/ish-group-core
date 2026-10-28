@php
    $can = fn (string $p) => \App\Helpers\AuthHelper::can($p);
    $S = \Modules\Purchasing\Enums\PurchaseStatus::class;
    $cell = 'w-28 rounded-lg border border-line bg-white px-2.5 py-2 text-sm text-right';
@endphp
<x-layouts.app :title="$order->number">
    <x-common.page-header :title="$order->number" :description="$order->supplier?->name">
        <x-slot:actions>
            <x-common.button href="{{ route('purchasing.orders.index') }}" variant="secondary" size="sm">← {{ __('purchasing::purchasing.orders') }}</x-common.button>
            @if ($order->status === $S::Draft && $can('update purchasing.order'))
                <x-common.button href="{{ route('purchasing.orders.edit', $order) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <form action="{{ route('purchasing.orders.receive', $order) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf
                @if ($order->status->canReceive() && $can('create purchasing.receipt'))
                    <p class="text-sm text-muted">{{ __('purchasing::purchasing.receive_hint', ['store' => $order->store?->name]) }}</p>
                @endif
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                        <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.line_item') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('purchasing::purchasing.ordered') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('purchasing::purchasing.received_qty') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('purchasing::purchasing.unit_cost') }}</th>
                        <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_total') }}</th>
                        @if ($order->status->canReceive())<th class="px-3 py-2.5 font-semibold text-right">{{ __('purchasing::purchasing.now') }}</th>@endif
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($order->lines as $line)
                            <tr>
                                <td class="px-3 py-3 font-medium text-ink">{{ $line->variant?->displayName() }}</td>
                                <td class="px-3 py-3 text-right">@include('inventory::partials.qty', ['value' => $line->quantity]) {{ $line->variant?->item?->unit }}</td>
                                <td class="px-3 py-3 text-right">@include('inventory::partials.qty', ['value' => $line->received_quantity])</td>
                                <td class="px-3 py-3 text-right">{{ money($line->unit_cost) }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ money($line->line_total) }}</td>
                                @if ($order->status->canReceive())
                                    <td class="px-3 py-3 text-right"><input type="number" step="any" min="0" max="{{ $line->remaining() }}" name="quantities[{{ $line->id }}]" value="{{ old('quantities.'.$line->id) }}" class="{{ $cell }}" @disabled($line->remaining() <= 0)></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="4" class="px-3 pt-3 text-right font-bold">{{ __('inventory::inventory.total') }}</td><td class="px-3 pt-3 text-right font-bold">{{ money($order->total) }}</td></tr></tfoot>
                </table>
                </div>
                @if ($order->status->canReceive() && $can('create purchasing.receipt'))
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                        <x-common.input name="received_on" type="date" :label="__('purchasing::purchasing.received_on')" :value="now()->toDateString()" required />
                        <x-common.input name="supplier_reference" :label="__('purchasing::purchasing.supplier_reference')" />
                        <x-common.input name="note" :label="__('purchasing::purchasing.note')" />
                    </div>
                    <x-common.button type="submit">{{ __('purchasing::purchasing.receive') }}</x-common.button>
                @endif
            </form>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('purchasing::purchasing.receipts') }}</h2></div>
                <table class="w-full text-sm"><tbody class="divide-y divide-line">
                    @forelse ($order->receipts as $receipt)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $receipt->number }}</td>
                            <td class="px-5 py-3 text-muted">{{ $receipt->received_on->format('d/m/Y') }}{{ $receipt->supplier_reference ? ' · '.$receipt->supplier_reference : '' }}</td>
                            <td class="px-5 py-3 text-right">{{ money($receipt->value) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-center text-muted">{{ __('purchasing::purchasing.receipts_none') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.badge :color="$order->status->color()">{{ $order->status->label() }}</x-common.badge>
                </div>
                <p class="text-sm text-muted">{{ __('purchasing::purchasing.store') }}: {{ $order->store?->name }}</p>
                @if ($order->supplier)<a href="{{ route('purchasing.suppliers.show', $order->supplier) }}" class="text-sm font-semibold text-brand">{{ $order->supplier->name }} →</a>@endif
                @if ($order->status === $S::Draft && $can('approve purchasing.order'))
                    <form action="{{ route('purchasing.orders.send', $order) }}" method="POST" class="space-y-1">
                        @csrf
                        <x-common.button type="submit" class="w-full">{{ __('purchasing::purchasing.send') }}</x-common.button>
                        <p class="text-xs text-muted">{{ __('purchasing::purchasing.send_hint') }}</p>
                    </form>
                @endif
                @if ($order->status === $S::Cancelled)<p class="text-sm text-muted">{{ $order->cancel_reason }}</p>@endif
            </div>
            @if (in_array($order->status, [$S::Draft, $S::Ordered], true) && $can('delete purchasing.order'))
                <form action="{{ route('purchasing.orders.cancel', $order) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('purchasing::purchasing.cancel') }}</h2>
                    <x-common.input name="reason" :label="__('purchasing::purchasing.cancel_reason')" required />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('purchasing::purchasing.cancel') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
