@php
    $can = fn (string $p) => \App\Helpers\AuthHelper::can($p);
    $methods = collect(['cash', 'mobile_money', 'bank', 'cheque', 'other'])->mapWithKeys(fn ($m) => [$m => __('purchasing::purchasing.method_'.$m)])->all();
@endphp
<x-layouts.app :title="$supplier->name">
    <x-common.page-header :title="$supplier->name" :description="$supplier->contact_person">
        <x-slot:actions>
            <x-common.button href="{{ route('purchasing.suppliers.index') }}" variant="secondary" size="sm">← {{ __('purchasing::purchasing.suppliers') }}</x-common.button>
            @if ($can('update purchasing.supplier'))<x-common.button href="{{ route('purchasing.suppliers.edit', $supplier) }}" size="sm">{{ __('body.edit') }}</x-common.button>@endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <x-common.info-card :title="__('purchasing::purchasing.supplier')" :rows="[
                [__('body.phone'), $supplier->phone], [__('body.email_address'), $supplier->email],
                [__('purchasing::purchasing.tax_id'), $supplier->tax_id], [__('purchasing::purchasing.address'), $supplier->address],
            ]" />

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('purchasing::purchasing.orders') }}</h2>
                    @if ($can('create purchasing.order'))<x-common.button href="{{ route('purchasing.orders.create') }}" size="sm">+ {{ __('purchasing::purchasing.order_add') }}</x-common.button>@endif
                </div>
                <table class="w-full text-sm"><tbody class="divide-y divide-line">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('purchasing.orders.show', $order) }}" class="font-medium text-ink hover:text-brand">{{ $order->number }}</a></td>
                            <td class="px-5 py-3 text-muted">{{ $order->order_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3 text-right">{{ money($order->total) }}</td>
                            <td class="px-5 py-3"><x-common.badge :color="$order->status->color()">{{ $order->status->label() }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-center text-muted">{{ __('purchasing::purchasing.orders_none') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('purchasing::purchasing.payments') }}</h2></div>
                <table class="w-full text-sm"><tbody class="divide-y divide-line">
                    @forelse ($payments as $payment)
                        <tr class="{{ $payment->isReversed() ? 'opacity-60' : '' }}">
                            <td class="px-5 py-3 font-medium">{{ $payment->number }}</td>
                            <td class="px-5 py-3 text-muted">{{ $payment->paid_on->format('d/m/Y') }} · {{ $methods[$payment->method] ?? $payment->method }}</td>
                            <td class="px-5 py-3 text-right {{ $payment->isReversed() ? 'line-through' : 'font-semibold' }}">{{ money($payment->amount) }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($payment->isReversed())
                                    <x-common.badge color="muted">{{ __('purchasing::purchasing.reversed') }}</x-common.badge> <span class="text-xs text-muted">{{ $payment->reverse_reason }}</span>
                                @elseif ($can('delete purchasing.payment'))
                                    <details class="inline-block text-left">
                                        <summary class="cursor-pointer text-xs font-semibold text-danger">{{ __('purchasing::purchasing.reverse') }}</summary>
                                        <form action="{{ route('purchasing.suppliers.payments.reverse', [$supplier, $payment]) }}" method="POST" class="mt-2 flex gap-2">
                                            @csrf
                                            <input type="text" name="reason" required placeholder="{{ __('purchasing::purchasing.reason') }}" class="rounded-lg border border-line px-2 py-1 text-xs">
                                            <button type="submit" class="text-xs font-semibold text-danger">{{ __('purchasing::purchasing.reverse') }}</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-center text-muted">{{ __('purchasing::purchasing.payments_none') }}</td></tr>
                    @endforelse
                </tbody></table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('purchasing::purchasing.balance') }}</p>
                <p class="text-2xl font-display font-bold {{ $balance > 0 ? 'text-danger' : 'text-ink' }} mt-1">{{ money($balance) }}</p>
                <p class="text-xs text-muted">{{ __('purchasing::purchasing.balance_hint') }}</p>
            </div>
            @if ($balance > 0 && $can('create purchasing.payment'))
                <form action="{{ route('purchasing.suppliers.pay', $supplier) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('purchasing::purchasing.payment_add') }}</h2>
                    <x-common.input name="amount" type="number" step="any" min="0" :label="__('purchasing::purchasing.amount')" :value="$balance" required />
                    <x-common.input name="paid_on" type="date" :label="__('purchasing::purchasing.paid_on')" :value="now()->toDateString()" required />
                    <x-common.select name="method" :label="__('purchasing::purchasing.method')" :options="$methods" value="bank" hide-placeholder required />
                    <x-common.input name="reference" :label="__('purchasing::purchasing.reference')" />
                    <x-common.button type="submit" class="w-full">{{ __('purchasing::purchasing.payment_add') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
