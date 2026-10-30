@php $m = fn ($v, $c = null) => \Modules\Savings\Support\Money::format((int) $v, $c); $base = \Modules\Savings\Support\Money::baseCurrency(); @endphp
<x-layouts.app :title="__('savings::savings.overview')">
    <x-common.page-header :title="__('savings::savings.overview')" :description="__('savings::savings.overview_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create savings.transaction'))
                <x-common.button href="{{ route('savings.transactions.create') }}" size="sm">+ {{ __('savings::savings.new') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    @php
        $liquid = $accounts->where('is_liquid', true)->where('currency', $base);
        $totals = [
            'actual' => $liquid->sum(fn ($a) => $available[$a->id]['balance']),
            'committed' => $liquid->sum(fn ($a) => $available[$a->id]['commitments']),
            'available' => $liquid->sum(fn ($a) => $available[$a->id]['available']),
            'owed' => $owed->where('o.direction', 'receivable')->sum('outstanding'),
            'owe' => $owed->where('o.direction', 'payable')->sum('outstanding'),
            'restore' => $owed->where('o.direction', 'restore')->sum('outstanding'),
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        @foreach ([['actual', 'actual'], ['committed', 'committed'], ['available', 'available'], ['owed', 'owed_to_me'], ['owe', 'i_owe'], ['restore', 'to_put_back']] as [$key, $label])
            <div class="bg-white border border-line rounded-xl p-4 {{ $key === 'available' ? 'ring-2 ring-brand/30' : '' }}">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('savings::savings.'.$label) }}</p>
                <p class="text-lg font-display font-bold mt-1 {{ $key === 'available' && $totals[$key] < 0 ? 'text-danger' : 'text-ink' }}">{{ $m($totals[$key], $base) }}</p>
            </div>
        @endforeach
    </div>

    @if ($shortfalls->isNotEmpty() || $late > 0)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 mb-6 space-y-1 text-sm">
            <p class="font-semibold text-danger">{{ __('savings::savings.warnings') }}</p>
            @foreach ($shortfalls as $row)
                <p>• {{ $row['account']->name }}: {{ __('savings::savings.shortfall', ['date' => \Illuminate\Support\Carbon::parse($row['shortfall'])->format('d/m/Y')]) }} ({{ $m($row['lowest'], $row['account']->currency) }}) — <a href="{{ route('savings.forecast', ['account' => $row['account']->uuid]) }}" class="font-semibold text-brand">{{ __('savings::savings.forecast') }} →</a></p>
            @endforeach
            @if ($late > 0)<p>• {{ $late }} {{ __('savings::savings.late') }} — <a href="{{ route('savings.planning.index') }}" class="font-semibold text-brand">{{ __('savings::savings.planning') }} →</a></p>@endif
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex justify-between"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.accounts') }}</h2><a href="{{ route('savings.accounts.index') }}" class="text-sm font-semibold text-brand">{{ __('savings::savings.see_all') }} →</a></div>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted uppercase border-b border-line bg-surface/60">
                        <th class="px-5 py-2.5">{{ __('savings::savings.account') }}</th><th class="px-5 py-2.5 text-right">{{ __('savings::savings.balance') }}</th>
                        <th class="px-5 py-2.5 text-right">{{ __('savings::savings.committed') }}</th><th class="px-5 py-2.5 text-right">{{ __('savings::savings.available') }}</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($accounts as $account)
                            @php $a = $available[$account->id]; @endphp
                            <tr>
                                <td class="px-5 py-3"><a href="{{ route('savings.accounts.show', $account) }}" class="font-medium text-ink hover:text-brand">{{ $account->name }}</a><span class="block text-xs text-muted">{{ __('savings::savings.account_'.$account->type) }}</span></td>
                                <td class="px-5 py-3 text-right font-semibold">{{ $m($a['balance'], $account->currency) }}</td>
                                <td class="px-5 py-3 text-right text-muted">{{ $a['commitments'] + $a['earmarks'] + $a['minimum'] ? $m($a['commitments'] + $a['earmarks'] + $a['minimum'], $account->currency) : '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold {{ $a['available'] < 0 ? 'text-danger' : '' }}">{{ $account->is_liquid ? $m($a['available'], $account->currency) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-8 text-center text-muted" colspan="4">{{ __('savings::savings.none') }} <a href="{{ route('savings.accounts.index') }}" class="text-brand font-semibold">{{ __('savings::savings.account_add') }} →</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex justify-between"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.transactions') }}</h2><a href="{{ route('savings.transactions.index') }}" class="text-sm font-semibold text-brand">{{ __('savings::savings.see_all') }} →</a></div>
                @include('savings::transactions.table', ['transactions' => $recent])
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.upcoming') }}</h2>
                @forelse ($soon->take(10) as $e)
                    <div class="flex justify-between gap-2 text-sm">
                        <span>{{ $e->expected_date->format('d/m') }} · {{ $e->description }} <x-common.badge :color="$e->expected_date->isPast() && ! $e->expected_date->isToday() ? 'danger' : 'muted'">{{ $e->expected_date->isPast() && ! $e->expected_date->isToday() ? __('savings::savings.late') : __('savings::savings.confidence_'.$e->confidence) }}</x-common.badge></span>
                        <span class="{{ in_array($e->kind, ['income', 'repayment_in']) ? 'text-success' : 'text-ink' }} font-semibold">{{ $m($e->amount, $e->account?->currency) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('savings::savings.none') }}</p>
                @endforelse
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <div class="flex justify-between"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.this_period') }}</h2><span class="text-xs text-muted">{{ $periodLabel }}</span></div>
                @forelse ($budgetRows as $row)
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $row['category']->name }}</span><span class="{{ $row['over'] ? 'text-danger font-semibold' : 'text-muted' }}">{{ $m($row['spent']) }} / {{ $m($row['budgeted']) }}</span></div>
                        <div class="h-1.5 rounded-full bg-surface overflow-hidden mt-1"><div class="h-full {{ $row['percent'] >= 100 ? 'bg-danger' : ($row['percent'] >= 80 ? 'bg-warning' : 'bg-brand') }}" style="width: {{ min(100, $row['percent']) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-muted"><a href="{{ route('savings.budgets.index') }}" class="text-brand font-semibold">{{ __('savings::savings.budgets') }} →</a></p>
                @endforelse
            </div>

            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.obligations') }}</h2>
                @forelse ($owed->take(8) as $r)
                    <a href="{{ route('savings.obligations.show', $r['o']) }}" class="flex justify-between text-sm hover:text-brand">
                        <span>{{ $r['o']->counterparty ?: $r['o']->description }} <span class="text-xs text-muted">{{ __('savings::savings.direction_'.$r['o']->direction) }}</span></span>
                        <span class="font-semibold">{{ $m($r['outstanding'], $r['o']->currency) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-muted">{{ __('savings::savings.none') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
