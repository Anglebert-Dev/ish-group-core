@php $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $rec->account?->currency); $can = fn ($p) => \App\Helpers\AuthHelper::can($p); @endphp
<x-layouts.app :title="__('savings::savings.reconcile')">
    <x-common.page-header :title="$rec->account?->name.' · '.$rec->statement_date->format('d/m/Y')">
        <x-slot:actions><x-common.button href="{{ route('savings.reconcile.index') }}" variant="secondary" size="sm">← {{ __('savings::savings.reconcile') }}</x-common.button></x-slot:actions>
    </x-common.page-header>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.statement_balance') }}</p><p class="text-lg font-bold">{{ $m($rec->statement_balance) }}</p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.ledger_balance') }}</p><p class="text-lg font-bold">{{ $m($rec->ledger_balance) }}</p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.difference') }}</p><p class="text-lg font-bold {{ $rec->difference ? 'text-danger' : 'text-success' }}">{{ $m($rec->difference) }}</p></div>
        <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('body.status') }}</p><p class="mt-2"><x-common.badge>{{ __('savings::savings.status_'.$rec->status) }}</x-common.badge></p></div>
    </div>
    @if ($rec->explanation)<div class="bg-white border border-line rounded-xl p-4 mb-6 text-sm">{{ $rec->explanation }}</div>@endif

    @if ($rec->difference !== 0)
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            <div class="xl:col-span-2 bg-white border border-line rounded-xl p-5 space-y-4">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.look_here') }}</h2>
                @foreach (['same_amount' => 'same_amount', 'duplicates' => 'possible_duplicates', 'nearby' => 'around_date'] as $key => $label)
                    @if ($look[$key]->isNotEmpty())
                        <p class="text-sm font-semibold">{{ __('savings::savings.'.$label) }}</p>
                        @foreach ($look[$key] as $entry)
                            <a href="{{ route('savings.transactions.show', $entry->transaction) }}" class="flex justify-between text-sm hover:text-brand"><span>{{ $entry->effective_date->format('d/m/Y') }} · {{ $entry->transaction?->description }}</span>@include('savings::partials.money', ['v' => $entry->amount, 'c' => $rec->account?->currency, 'signed' => true])</a>
                        @endforeach
                    @endif
                @endforeach
            </div>
            @if (! in_array($rec->status, ['adjusted', 'matched']) && $can('update savings.reconciliation'))
                <div class="space-y-4">
                    <form action="{{ route('savings.reconcile.explain', $rec) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                        @csrf
                        <x-common.textarea name="explanation" :label="__('savings::savings.explain')" :rows="2" required />
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.explain') }}</x-common.button>
                    </form>
                    <form action="{{ route('savings.reconcile.adjust', $rec) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                        @csrf
                        <p class="text-xs text-muted">{{ __('savings::savings.post_adjustment_hint') }}</p>
                        <x-common.input name="reason" :label="__('savings::savings.reason')" required />
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.post_adjustment') }}</x-common.button>
                    </form>
                </div>
            @endif
        </div>
    @endif
</x-layouts.app>
