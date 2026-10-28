@php
    $can = fn (string $permission) => \App\Helpers\AuthHelper::can($permission);
    $Q = \Modules\Quotations\Enums\QuoteStatus::class;
    $S = \Modules\Quotations\Enums\SampleStatus::class;
    $open = in_array($quote->status, [$Q::Open, $Q::Accepted], true);
    $methods = collect(['cash', 'mobile_money', 'bank', 'cheque', 'card', 'other'])->mapWithKeys(fn ($m) => [$m => __('quotations::quotations.method_'.$m)])->all();
@endphp
<x-layouts.app :title="$quote->number">
    <x-common.page-header :title="$quote->number" :description="$quote->customer?->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ route('quotations.quotes.index') }}" variant="secondary" size="sm">← {{ __('quotations::quotations.quotes') }}</x-common.button>
            @if ($quote->status === $Q::Open && $can('update quotations.quote'))
                <x-common.button href="{{ route('quotations.quotes.edit', $quote) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.info-card :title="__('quotations::quotations.quote')" :rows="[
                    [__('quotations::quotations.quote_date'), $quote->quote_date->format('d/m/Y')],
                    [__('quotations::quotations.valid_until'), $quote->valid_until?->format('d/m/Y')],
                    [__('quotations::quotations.follow_up_date'), $quote->follow_up_date?->format('d/m/Y')],
                    [__('quotations::quotations.store'), $quote->store?->name],
                ]" />
                <x-common.info-card :title="__('quotations::quotations.customer')" :rows="[
                    [__('body.name'), $quote->customer?->displayName()],
                    [__('body.phone'), $quote->customer?->phone],
                    [__('body.email_address'), $quote->customer?->email],
                ]" />
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('quotations::quotations.lines') }}</h2>
                @include('salesorders::orders.lines-table', ['order' => $quote])
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-4">
                <div>
                    <h2 class="font-display font-bold text-ink">{{ __('quotations::quotations.deposits') }}</h2>
                    <p class="text-sm text-muted">{{ __('quotations::quotations.deposits_hint') }}</p>
                </div>
                @forelse ($quote->deposits as $deposit)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-2 text-sm {{ $deposit->isReversed() ? 'opacity-60' : '' }}">
                        <span>{{ $deposit->paid_on->format('d/m/Y') }} · {{ $methods[$deposit->method] ?? $deposit->method }}@if ($deposit->reference) · {{ $deposit->reference }}@endif</span>
                        <span class="flex items-center gap-2">
                            <b class="{{ $deposit->isReversed() ? 'line-through' : 'text-success' }}">{{ money($deposit->amount) }}</b>
                            @if ($deposit->isReversed())
                                <x-common.badge color="muted">{{ __('quotations::quotations.reversed') }}</x-common.badge>
                                <span class="text-xs text-muted">{{ $deposit->reverse_reason }}</span>
                            @elseif ($deposit->applied_invoice_id)
                                <x-common.badge color="success">{{ __('quotations::quotations.deposit_applied') }}</x-common.badge>
                            @elseif ($can('delete quotations.deposit'))
                                <details class="inline-block">
                                    <summary class="cursor-pointer text-xs font-semibold text-danger">{{ __('quotations::quotations.reverse') }}</summary>
                                    <form action="{{ route('quotations.quotes.deposits.reverse', [$quote, $deposit]) }}" method="POST" class="mt-2 flex gap-2">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="{{ __('quotations::quotations.reason') }}" class="rounded-lg border border-line px-2 py-1 text-xs">
                                        <button type="submit" class="text-xs font-semibold text-danger">{{ __('quotations::quotations.reverse') }}</button>
                                    </form>
                                </details>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('quotations::quotations.deposits_none') }}</p>
                @endforelse
                <p class="text-sm font-semibold text-ink">{{ __('quotations::quotations.deposits_total', ['amount' => money($quote->depositTotal())]) }}</p>

                @if ($quote->status !== $Q::Rejected && $can('create quotations.deposit'))
                    <details>
                        <summary class="cursor-pointer text-sm font-semibold text-brand">+ {{ __('quotations::quotations.deposit_add') }}</summary>
                        <form action="{{ route('quotations.quotes.deposits.store', $quote) }}" method="POST" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @csrf
                            <x-common.input name="amount" type="number" step="any" min="0" :label="__('quotations::quotations.amount')" required />
                            <x-common.input name="paid_on" type="date" :label="__('quotations::quotations.quote_date')" :value="now()->toDateString()" required />
                            <x-common.select name="method" :label="__('quotations::quotations.method')" :options="$methods" value="cash" hide-placeholder required />
                            <x-common.input name="reference" :label="__('quotations::quotations.reference')" />
                            <div><x-common.button type="submit" size="sm">{{ __('quotations::quotations.deposit_add') }}</x-common.button></div>
                        </form>
                    </details>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.badge :color="$quote->status->color()">{{ $quote->status->label() }}</x-common.badge>
                </div>
                @if ($quote->isExpired())<x-common.badge color="danger">{{ __('quotations::quotations.expired') }}</x-common.badge>@endif
                @if ($quote->needsFollowUp())<x-common.badge color="warning">{{ __('quotations::quotations.follow_up_due') }}</x-common.badge>@endif

                @if ($open && $can('update quotations.quote'))
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs text-muted">{{ __('quotations::quotations.mark') }}</span>
                        @foreach ([$Q::Open, $Q::Accepted, $Q::Rejected] as $next)
                            @continue($next === $quote->status)
                            <x-common.action-button :action="route('quotations.quotes.status', $quote)" method="PUT" :color="$next === $Q::Rejected ? 'danger' : 'muted'">
                                <x-slot:fields><input type="hidden" name="status" value="{{ $next->value }}"></x-slot:fields>
                                {{ $next->label() }}
                            </x-common.action-button>
                        @endforeach
                    </div>
                @endif

                @if ($quote->salesOrder)
                    <a href="{{ route('salesorders.orders.show', $quote->salesOrder) }}" class="text-sm font-semibold text-brand">{{ $quote->salesOrder->number }} →</a>
                @elseif ($open && $can('approve quotations.quote') && $can('create sales.order'))
                    <form action="{{ route('quotations.quotes.convert', $quote) }}" method="POST" class="space-y-1">
                        @csrf
                        <x-common.button type="submit" class="w-full">{{ __('quotations::quotations.convert') }}</x-common.button>
                        <p class="text-xs text-muted">{{ __('quotations::quotations.convert_hint') }}</p>
                    </form>
                @endif
            </div>

            @if ($quote->is_sample)
                <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="font-display font-bold text-ink">{{ __('quotations::quotations.sample') }}</h2>
                        <x-common.badge :color="$quote->sample_status->color()">{{ $quote->sample_status->label() }}</x-common.badge>
                    </div>
                    @if ($can('update quotations.quote') && $quote->sample_status !== $S::Returned)
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-muted">{{ __('quotations::quotations.sample_set') }}</span>
                            @foreach ([$S::Sent, $S::Approved, $S::Rejected, $S::Returned] as $next)
                                @continue($next === $quote->sample_status)
                                <x-common.action-button :action="route('quotations.quotes.sample', $quote)" method="PUT" color="muted">
                                    <x-slot:fields><input type="hidden" name="sample_status" value="{{ $next->value }}"></x-slot:fields>
                                    {{ $next->label() }}
                                </x-common.action-button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
