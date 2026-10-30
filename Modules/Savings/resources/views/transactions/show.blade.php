@php $m = fn ($v, $c = null) => \Modules\Savings\Support\Money::format((int) $v, $c ?? $tx->currency); $can = fn ($p) => \App\Helpers\AuthHelper::can($p); @endphp
<x-layouts.app :title="$tx->description">
    <x-common.page-header :title="$tx->description" :description="__('savings::savings.type_'.$tx->type).' · '.$tx->effective_date->format('d/m/Y')">
        <x-slot:actions><x-common.button href="{{ route('savings.transactions.index') }}" variant="secondary" size="sm">← {{ __('savings::savings.transactions') }}</x-common.button></x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            @if ($tx->isReversed() || $tx->isReversal() || $replaces || $replacedBy || $tx->refundOf)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm space-y-1">
                    @if ($tx->isReversed())<p>{{ __('savings::savings.reversed_note', ['date' => $tx->reversed_at->format('d/m/Y'), 'reason' => $tx->reversal_reason]) }} @if ($tx->reversal)<a class="font-semibold text-brand" href="{{ route('savings.transactions.show', $tx->reversal) }}">→</a>@endif</p>@endif
                    @if ($tx->isReversal())<p>{{ __('savings::savings.reversal') }}: <a class="font-semibold text-brand" href="{{ route('savings.transactions.show', $tx->reverses) }}">{{ $tx->reverses?->description }}</a></p>@endif
                    @if ($replaces)<p>{{ __('savings::savings.replaces') }}: <a class="font-semibold text-brand" href="{{ route('savings.transactions.show', $replaces) }}">{{ $replaces->description }}</a></p>@endif
                    @if ($replacedBy)<p>{{ __('savings::savings.correct') }}: <a class="font-semibold text-brand" href="{{ route('savings.transactions.show', $replacedBy) }}">{{ $replacedBy->description }}</a></p>@endif
                    @if ($tx->refundOf)<p>{{ __('savings::savings.refund_of') }}: <a class="font-semibold text-brand" href="{{ route('savings.transactions.show', $tx->refundOf) }}">{{ $tx->refundOf->description }}</a></p>@endif
                </div>
            @endif

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.entries') }}</h2>
                @foreach ($tx->entries as $entry)
                    <div class="flex justify-between text-sm"><a href="{{ route('savings.accounts.show', $entry->account) }}" class="hover:text-brand">{{ $entry->account?->name }}</a>@include('savings::partials.money', ['v' => $entry->amount, 'c' => $entry->account?->currency, 'signed' => true])</div>
                @endforeach
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.lines') }}</h2>
                @forelse ($tx->lines as $line)
                    <div class="flex justify-between text-sm"><span>{{ __('savings::savings.nature_'.$line->nature) }}{{ $line->category ? ': '.$line->category->fullName() : '' }}{{ $line->note ? ' · '.$line->note : '' }}</span><span>{{ $m($line->amount) }}</span></div>
                @empty
                    <p class="text-sm text-muted">{{ __('savings::savings.help_transfer') }}</p>
                @endforelse
                @foreach ($obligations as $o)
                    <a href="{{ route('savings.obligations.show', $o) }}" class="block text-sm font-semibold text-brand">{{ $o->counterparty ?: $o->description }} →</a>
                @endforeach
            </div>

            <x-common.info-card :title="__('savings::savings.transaction')" :rows="[
                [__('savings::savings.counterparty'), $tx->counterparty],
                [__('savings::savings.reference'), $tx->reference],
                [__('savings::savings.tags'), implode(', ', $tx->tags ?? [])],
                [__('savings::savings.foreign'), $tx->original_amount ? $m($tx->original_amount, $tx->original_currency).' @ '.$tx->exchange_rate : null],
                [__('savings::savings.source'), $tx->source],
                [__('savings::savings.notes'), $tx->notes],
                [__('savings::savings.by'), $tx->user?->name],
            ]" />
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.amount') }}</p><p class="text-2xl font-display font-bold text-ink">{{ $m($tx->amount) }}</p></div>

            @if (! $tx->isReversed() && ! $tx->isReversal())
                @if ($tx->lines->whereIn('nature', ['expense', 'income'])->isNotEmpty() && $can('create savings.transaction'))
                    <details class="bg-white border border-line rounded-xl p-5">
                        <summary class="cursor-pointer font-display font-bold text-ink">{{ __('savings::savings.type_refund') }}</summary>
                        <form action="{{ route('savings.transactions.refund', $tx) }}" method="POST" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $key }}">
                            <x-common.select name="account" :label="__('savings::savings.account')" :value="$tx->entries->first()?->account?->uuid" :options="$accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all()" hide-placeholder />
                            <x-common.input name="amount" :label="__('savings::savings.amount')" required />
                            <x-common.input name="effective_date" type="date" :label="__('savings::savings.date')" :value="now()->toDateString()" required />
                            <x-common.button type="submit" size="sm">{{ __('savings::savings.save') }}</x-common.button>
                        </form>
                    </details>
                @endif
                @if ($can('delete savings.transaction'))
                    <form action="{{ route('savings.transactions.reverse', $tx) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                        @csrf
                        <h2 class="font-display font-bold text-danger">{{ __('savings::savings.reverse') }} / {{ __('savings::savings.correct') }}</h2>
                        <p class="text-xs text-muted">{{ __('savings::savings.reverse_hint') }}</p>
                        <x-common.input name="reason" :label="__('savings::savings.reason')" required />
                        <input type="hidden" name="correct" value="0">
                        <x-common.checkbox name="correct" value="1" :label="__('savings::savings.correct_hint')" />
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.reverse') }}</x-common.button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</x-layouts.app>
