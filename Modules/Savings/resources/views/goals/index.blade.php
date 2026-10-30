@php $can = fn ($p) => \App\Helpers\AuthHelper::can($p); $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all(); @endphp
<x-layouts.app :title="__('savings::savings.goals')">
    <x-common.page-header :title="__('savings::savings.goals')" />
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse ($goals as $r)
                @php
                    $g = $r['g']; $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $g->currency);
                    $pf = $g->target_amount > 0 ? min(100, intdiv(max(0, $r['funded']) * 100, $g->target_amount)) : 0;
                    $pe = $g->target_amount > 0 ? min(100, intdiv(max(0, $r['earmarked']) * 100, $g->target_amount)) : 0;
                @endphp
                <div class="bg-white border border-line rounded-xl p-5 space-y-3 {{ $g->status !== 'active' ? 'opacity-60' : '' }}">
                    <div class="flex justify-between"><h2 class="font-display font-bold text-ink">{{ $g->name }}</h2><span class="text-sm text-muted">{{ $g->target_date?->format('d/m/Y') }}</span></div>
                    <p class="text-sm">{{ __('savings::savings.target') }}: <b>{{ $m($g->target_amount) }}</b>{{ $g->account ? ' · '.__('savings::savings.held_in').' '.$g->account->name : '' }}</p>
                    <div class="h-2.5 rounded-full bg-surface overflow-hidden relative">
                        <div class="absolute inset-y-0 left-0 bg-brand/30" style="width: {{ $pe }}%"></div>
                        <div class="absolute inset-y-0 left-0 bg-brand" style="width: {{ $pf }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs"><span>{{ __('savings::savings.funded') }}: <b>{{ $m($r['funded']) }}</b></span><span>{{ __('savings::savings.earmarked') }}: <b>{{ $m($r['earmarked']) }}</b></span></div>
                    @if ($r['missed'])<p class="text-xs text-danger">{{ __('savings::savings.missed') }}: {{ $r['missed'] }}</p>@endif
                    @if ($can('update savings.goal') && $g->status === 'active')
                        <details><summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('savings::savings.allocate') }}</summary>
                            <form action="{{ route('savings.goals.allocate', $g) }}" method="POST" class="mt-2 space-y-2">@csrf
                                <p class="text-xs text-muted">{{ __('savings::savings.allocate_hint') }}</p>
                                <x-common.input name="amount" :id="'al-'.$g->id" :label="__('savings::savings.amount')" hint="+ / −" required compact />
                                <x-common.input name="note" :id="'an-'.$g->id" :label="__('savings::savings.notes')" compact />
                                <x-common.button type="submit" size="sm">{{ __('savings::savings.allocate') }}</x-common.button>
                            </form></details>
                        @if ($g->account && $can('create savings.transaction'))
                            <details><summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('savings::savings.contribute') }}</summary>
                                <form action="{{ route('savings.goals.contribute', $g) }}" method="POST" class="mt-2 space-y-2">@csrf
                                    <input type="hidden" name="idempotency_key" value="{{ \Modules\Savings\Services\Ledger::key() }}">
                                    <p class="text-xs text-muted">{{ __('savings::savings.contribute_hint') }}</p>
                                    <x-common.select name="account" :id="'ca-'.$g->id" :label="__('savings::savings.from')" :options="collect($accountOptions)->except($g->account->uuid)->all()" hide-placeholder compact />
                                    <x-common.input name="amount" :id="'cm-'.$g->id" :label="__('savings::savings.amount')" required compact />
                                    <x-common.input name="effective_date" :id="'cd-'.$g->id" type="date" :label="__('savings::savings.date')" :value="now()->toDateString()" compact />
                                    <x-common.button type="submit" size="sm">{{ __('savings::savings.contribute') }}</x-common.button>
                                </form></details>
                        @endif
                        @if ($goals->count() > 1)
                            <details><summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('savings::savings.reassign') }}</summary>
                                <form action="{{ route('savings.goals.reassign', $g) }}" method="POST" class="mt-2 space-y-2">@csrf
                                    <x-common.select name="to" :id="'rt-'.$g->id" :label="__('savings::savings.to')" :options="$goals->pluck('g')->reject(fn ($x) => $x->is($g))->mapWithKeys(fn ($x) => [$x->uuid => $x->name])->all()" hide-placeholder compact />
                                    <x-common.input name="amount" :id="'rm-'.$g->id" :label="__('savings::savings.amount')" required compact />
                                    <x-common.input name="note" :id="'rn-'.$g->id" :label="__('savings::savings.reason')" required compact />
                                    <x-common.button type="submit" size="sm">{{ __('savings::savings.reassign') }}</x-common.button>
                                </form></details>
                        @endif
                    @endif
                </div>
            @empty
                <div class="bg-white border border-line rounded-xl p-10 text-center text-muted md:col-span-2">{{ __('savings::savings.none') }}</div>
            @endforelse
        </div>
        @if ($can('create savings.goal'))
            <form action="{{ route('savings.goals.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.goal_add') }}</h2>
                <x-common.input name="name" :label="__('body.name')" required />
                <x-common.input name="target_amount" :label="__('savings::savings.target')" required />
                <x-common.input name="currency" :label="__('savings::savings.currency')" :value="$currency" maxlength="3" required />
                <x-common.input name="target_date" type="date" :label="__('savings::savings.target_date')" />
                <x-common.select name="account" :label="__('savings::savings.held_in')" :options="['' => '—'] + $accountOptions" hide-placeholder />
                <x-common.input name="monthly_contribution" :label="__('savings::savings.monthly_contribution')" />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
