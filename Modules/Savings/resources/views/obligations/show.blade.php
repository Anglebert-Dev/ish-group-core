@php
    $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $o->currency);
    $can = fn ($p) => \App\Helpers\AuthHelper::can($p);
    $open = $o->status !== 'written_off';
    $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all();
@endphp
<x-layouts.app :title="$o->counterparty ?: $o->description">
    <x-common.page-header :title="$o->counterparty ?: $o->description" :description="__('savings::savings.direction_'.$o->direction).' · '.$o->description">
        <x-slot:actions><x-common.button href="{{ route('savings.obligations.index') }}" variant="secondary" size="sm">← {{ __('savings::savings.obligations') }}</x-common.button></x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.original') }}</p><p class="text-lg font-display font-bold">{{ $m($original) }}</p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.outstanding') }}</p><p class="text-lg font-display font-bold {{ $outstanding > 0 ? 'text-danger' : 'text-success' }}">{{ $m($outstanding) }}</p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('body.status') }}</p><p class="mt-2"><x-common.badge>{{ __('savings::savings.state_'.$state) }}</x-common.badge></p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.due_date') }}</p><p class="text-lg font-display font-bold">{{ $o->due_date?->format('d/m/Y') ?? '—' }}</p></div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.explanation') }}</h2>
                @forelse ($history as $h)
                    <div class="flex justify-between gap-3 text-sm border-b border-line pb-1.5">
                        <span><span class="text-muted">{{ \Illuminate\Support\Carbon::parse($h['date'])->format('d/m/Y') }}</span> · {{ $h['label'] }}
                            @if ($h['transaction'])<a href="{{ route('savings.transactions.show', $h['transaction']) }}" class="text-brand text-xs">{{ $h['transaction']->description }} →</a>@endif</span>
                        @include('savings::partials.money', ['v' => $h['amount'], 'c' => $o->currency, 'signed' => true])
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('savings::savings.none') }}</p>
                @endforelse
                <div class="flex justify-between text-sm font-bold pt-1"><span>{{ __('savings::savings.outstanding') }}</span><span>{{ $m($outstanding) }}</span></div>
            </div>
            @if ($o->notes)<div class="bg-white border border-line rounded-xl p-5 text-sm"><h2 class="font-display font-bold text-ink mb-1">{{ __('savings::savings.evidence') }}</h2>{!! nl2br(e($o->notes)) !!}</div>@endif

            @if ($o->direction === 'restore' && $open && $can('update savings.obligation'))
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form action="{{ route('savings.obligations.basis', $o) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                        @csrf
                        <h2 class="font-display font-bold text-ink">{{ __('savings::savings.add_basis') }}</h2>
                        <x-common.select name="transaction" id="b-tx" :label="__('savings::savings.transaction')" :options="$outgoing->mapWithKeys(fn ($t) => [$t->uuid => $t->effective_date->format('d/m').' · '.$t->description.' · '.$m($t->amount)])->all()" required />
                        <x-common.input name="amount" id="b-amt" :label="__('savings::savings.amount')" required />
                        <x-common.input name="note" id="b-note" :label="__('savings::savings.notes')" />
                        <x-common.button type="submit" size="sm">{{ __('savings::savings.add_basis') }}</x-common.button>
                    </form>
                    <form action="{{ route('savings.obligations.link', $o) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                        @csrf
                        <h2 class="font-display font-bold text-ink">{{ __('savings::savings.link_restore') }}</h2>
                        <x-common.select name="transaction" id="l-tx" :label="__('savings::savings.transaction')" :options="$incoming->mapWithKeys(fn ($t) => [$t->uuid => $t->effective_date->format('d/m').' · '.$t->description.' · '.$m($t->amount)])->all()" required />
                        <x-common.input name="amount" id="l-amt" :label="__('savings::savings.amount')" required />
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.link_restore') }}</x-common.button>
                    </form>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if ($open && $outstanding > 0 && $can('create savings.transaction'))
                <form action="{{ route('savings.obligations.repay', $o) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $key }}">
                    <h2 class="font-display font-bold text-ink">{{ $o->direction === 'restore' ? __('savings::savings.put_back') : __('savings::savings.record_repayment') }}</h2>
                    <x-common.select name="account" id="r-acc" :label="$o->direction === 'receivable' ? __('savings::savings.receive_into') : __('savings::savings.pay_from')" :value="$o->direction === 'receivable' ? $o->account?->uuid : null" :options="$accountOptions" hide-placeholder required />
                    <x-common.input name="amount" id="r-amt" :label="$o->direction === 'payable' ? __('savings::savings.principal') : __('savings::savings.amount')" :value="\Modules\Savings\Support\Money::toDecimal($outstanding, $o->currency)" required />
                    @if ($o->direction === 'payable')
                        <x-common.input name="interest" id="r-int" :label="__('savings::savings.interest')" />
                        <x-common.select name="interest_category" id="r-intc" :label="__('savings::savings.category')" :options="['' => '—'] + $expenseCategories->mapWithKeys(fn ($c) => [$c->uuid => $c->name])->all()" hide-placeholder />
                    @endif
                    <x-common.input name="effective_date" id="r-date" type="date" :label="__('savings::savings.date')" :value="now()->toDateString()" required />
                    <x-common.button type="submit" class="w-full">{{ __('body.save') }}</x-common.button>
                </form>
            @endif
            @if ($open && $can('update savings.obligation'))
                <form action="{{ route('savings.obligations.adjust', $o) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('savings::savings.adjust') }}</h2>
                    <x-common.input name="amount" id="a-amt" :label="__('savings::savings.amount')" hint="+ / −" required />
                    <x-common.input name="reason" id="a-reason" :label="__('savings::savings.reason')" required />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.adjust') }}</x-common.button>
                </form>
                <form action="{{ route('savings.obligations.dispute', $o) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-2">
                    @csrf
                    <x-common.input name="reason" id="d-reason" :label="__('savings::savings.reason')" />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ $o->status === 'disputed' ? __('savings::savings.undispute') : __('savings::savings.dispute') }}</x-common.button>
                </form>
            @endif
            @if ($open && $can('delete savings.obligation'))
                <form action="{{ route('savings.obligations.write-off', $o) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('savings::savings.write_off') }}</h2>
                    <x-common.input name="reason" id="w-reason" :label="__('savings::savings.reason')" required />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.write_off') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
