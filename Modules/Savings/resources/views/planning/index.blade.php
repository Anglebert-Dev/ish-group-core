@php
    $m = fn ($v, $c = null) => \Modules\Savings\Support\Money::format((int) $v, $c);
    $can = fn ($p) => \App\Helpers\AuthHelper::can($p);
    $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all();
    $catOptions = $categories->mapWithKeys(fn ($c) => [$c->uuid => __('savings::savings.kind_'.$c->kind).': '.$c->fullName()])->all();
    $goalOptions = $goals->mapWithKeys(fn ($g) => [$g->uuid => $g->name])->all();
    $kinds = collect(\Modules\Savings\Http\Controllers\PlanningController::KINDS)->mapWithKeys(fn ($k) => [$k => __('savings::savings.kind_'.$k)])->all();
    $confidence = collect(['confirmed', 'expected', 'uncertain'])->mapWithKeys(fn ($c) => [$c => __('savings::savings.confidence_'.$c)])->all();
    $expenseOptions = $categories->where('kind', 'expense')->mapWithKeys(fn ($c) => [$c->uuid => $c->fullName()])->all();
@endphp
<x-layouts.app :title="__('savings::savings.planning')">
    <x-common.page-header :title="__('savings::savings.planning')" />

    @foreach ($groups as $label => $g)
        <div class="rounded-xl border border-brand/30 bg-brand-light/50 p-3 mb-3 text-sm"><b>{{ $label }}</b>: {{ __('savings::savings.received_so_far', ['received' => $m($g['received']), 'total' => $m($g['total']), 'remaining' => $m($g['total'] - $g['received'])]) }}</div>
    @endforeach

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.expected') }}</h2></div>
                <div class="divide-y divide-line">
                    @forelse ($expected as $e)
                        @php $late = $e->expected_date->lt(now()->startOfDay()); @endphp
                        <details class="px-5 py-3">
                            <summary class="cursor-pointer flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span><b class="{{ $late ? 'text-danger' : '' }}">{{ $e->expected_date->format('d/m/Y') }}</b> · {{ $e->description }}
                                    <span class="text-xs text-muted">{{ __('savings::savings.kind_'.$e->kind) }} · {{ $e->account?->name }}{{ $e->toAccount ? ' → '.$e->toAccount->name : '' }}</span>
                                    <x-common.badge :color="$late ? 'danger' : ($e->confidence === 'uncertain' ? 'warning' : 'muted')">{{ $late ? __('savings::savings.late') : __('savings::savings.confidence_'.$e->confidence) }}</x-common.badge></span>
                                <span class="font-semibold {{ in_array($e->kind, ['income', 'repayment_in']) ? 'text-success' : '' }}">{{ $m($e->amount, $e->account?->currency) }}</span>
                            </summary>
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                                @if ($can('create savings.transaction'))
                                    <form action="{{ route('savings.planning.expected.post', $e) }}" method="POST" class="space-y-2">
                                        @csrf
                                        <p class="text-xs text-muted">{{ __('savings::savings.mark_done_hint') }}</p>
                                        <x-common.input name="amount" :id="'pa-'.$e->id" :label="__('savings::savings.amount')" :value="\Modules\Savings\Support\Money::toDecimal($e->amount, $e->account?->currency ?? 'RWF')" compact />
                                        <x-common.input name="effective_date" :id="'pd-'.$e->id" type="date" :label="__('savings::savings.date')" :value="now()->toDateString()" compact />
                                        <x-common.button type="submit" size="sm">{{ __('savings::savings.mark_done') }}</x-common.button>
                                    </form>
                                @endif
                                <form action="{{ route('savings.planning.expected.match', $e) }}" method="POST" class="space-y-2">
                                    @csrf
                                    <p class="text-xs text-muted">{{ __('savings::savings.match_hint') }}</p>
                                    <x-common.select name="transaction" :id="'m-'.$e->id" :label="__('savings::savings.transaction')" compact
                                        :options="$recent->mapWithKeys(fn ($t) => [$t->uuid => $t->effective_date->format('d/m').' · '.$t->description.' · '.$m($t->amount, $t->currency)])->all()" />
                                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.match') }}</x-common.button>
                                </form>
                                <div class="space-y-3">
                                    <form action="{{ route('savings.planning.expected.update', $e) }}" method="POST" class="space-y-2">
                                        @csrf @method('PUT')
                                        <x-common.input name="expected_date" :id="'ed-'.$e->id" type="date" :label="__('savings::savings.expected_date')" :value="$e->expected_date->toDateString()" compact />
                                        <input type="hidden" name="amount" value="{{ \Modules\Savings\Support\Money::toDecimal($e->amount, $e->account?->currency ?? 'RWF') }}">
                                        <x-common.select name="confidence" :id="'ec-'.$e->id" :label="__('savings::savings.confidence')" :value="$e->confidence" :options="$confidence" hide-placeholder compact />
                                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('body.save') }}</x-common.button>
                                    </form>
                                    <form action="{{ route('savings.planning.expected.skip', $e) }}" method="POST" class="flex gap-2 items-end">
                                        @csrf
                                        <x-common.input name="reason" :id="'sr-'.$e->id" :label="__('savings::savings.reason')" compact required />
                                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.skip') }}</x-common.button>
                                    </form>
                                </div>
                            </div>
                        </details>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-muted">{{ __('savings::savings.none') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.recurring') }}</h2></div>
                <div class="divide-y divide-line">
                    @forelse ($rules as $rule)
                        <details class="px-5 py-3 {{ $rule->is_active ? '' : 'opacity-60' }}">
                            <summary class="cursor-pointer flex justify-between text-sm"><span>{{ $rule->description }} <span class="text-xs text-muted">{{ __('savings::savings.every', ['n' => $rule->interval, 'unit' => __('savings::savings.freq_'.$rule->frequency)]) }} · {{ $rule->account?->name }}</span></span><b>{{ $m($rule->amount, $rule->account?->currency) }}</b></summary>
                            @if ($can('update savings.planning'))
                                <form action="{{ route('savings.planning.rules.update', $rule) }}" method="POST" class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                                    @csrf @method('PUT')
                                    @include('savings::planning.rule-fields', ['rule' => $rule, 'p' => 'r'.$rule->id.'-'])
                                    <input type="hidden" name="is_active" value="0">
                                    <x-common.checkbox name="is_active" :id="'ra-'.$rule->id" value="1" :label="__('savings::savings.active')" :checked="$rule->is_active" />
                                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                                </form>
                            @endif
                        </details>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-muted">{{ __('savings::savings.none') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('savings::savings.commitments') }}</h2><p class="text-xs text-muted">{{ __('savings::savings.commitments_hint') }}</p></div>
                <div class="divide-y divide-line">
                    @forelse ($commitments as $c)
                        <div class="px-5 py-3 flex flex-wrap items-center justify-between gap-2 text-sm {{ $c->status !== 'open' ? 'opacity-60' : '' }}">
                            <span>{{ $c->description }} <span class="text-xs text-muted">{{ $c->account?->name }}{{ $c->due_date ? ' · '.$c->due_date->format('d/m/Y') : '' }} · {{ $c->status }}</span></span>
                            <span class="flex items-center gap-2"><b>{{ $m($c->amount, $c->account?->currency) }}</b>
                                @if ($c->status === 'open' && $can('update savings.planning'))
                                    <form action="{{ route('savings.planning.commitments.settle', $c) }}" method="POST" class="flex gap-1">
                                        @csrf
                                        <select name="category" class="rounded-lg border border-line px-2 py-1 text-xs" required>@foreach ($expenseOptions as $uuid => $name)<option value="{{ $uuid }}">{{ $name }}</option>@endforeach</select>
                                        <button class="text-xs font-semibold text-brand">{{ __('savings::savings.settle') }}</button>
                                    </form>
                                    <form action="{{ route('savings.planning.commitments.cancel', $c) }}" method="POST">@csrf<button class="text-xs font-semibold text-danger">{{ __('savings::savings.cancel') }}</button></form>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-center text-sm text-muted">{{ __('savings::savings.none') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($can('create savings.planning'))
            <div class="space-y-4">
                <form action="{{ route('savings.planning.expected.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3" data-parts>
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('savings::savings.expected_add') }}</h2>
                    <x-common.select name="kind" id="x-kind" :label="__('savings::savings.kind')" :options="$kinds" hide-placeholder required />
                    <x-common.input name="description" id="x-desc" :label="__('savings::savings.description')" required />
                    <x-common.select name="account" id="x-acc" :label="__('savings::savings.account')" :options="$accountOptions" hide-placeholder required />
                    <x-common.select name="to_account" id="x-to" :label="__('savings::savings.to_account')" :options="['' => '—'] + $accountOptions" hide-placeholder />
                    <x-common.select name="category" id="x-cat" :label="__('savings::savings.category')" :options="['' => '—'] + $catOptions" hide-placeholder />
                    <x-common.select name="goal" id="x-goal" :label="__('savings::savings.goal')" :options="['' => '—'] + $goalOptions" hide-placeholder />
                    <x-common.select name="confidence" id="x-conf" :label="__('savings::savings.confidence')" :options="$confidence" hide-placeholder />
                    <p class="text-xs text-muted">{{ __('savings::savings.instalments_hint') }}</p>
                    <div data-part-rows class="space-y-2">
                        <div class="grid grid-cols-2 gap-2"><input type="date" name="parts[0][date]" value="{{ now()->toDateString() }}" class="rounded-lg border border-line px-2 py-1.5 text-sm" required><input type="text" name="parts[0][amount]" placeholder="{{ __('savings::savings.amount') }}" class="rounded-lg border border-line px-2 py-1.5 text-sm" required></div>
                    </div>
                    <button type="button" data-add-part class="text-xs font-semibold text-brand">+ {{ __('savings::savings.instalment_add') }}</button>
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>

                <form action="{{ route('savings.planning.rules.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('savings::savings.rule_add') }}</h2>
                    @include('savings::planning.rule-fields', ['rule' => null, 'p' => 'new-'])
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>

                <form action="{{ route('savings.planning.commitments.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('savings::savings.commitment_add') }}</h2>
                    <x-common.select name="account" id="c-acc" :label="__('savings::savings.account')" :options="$accountOptions" hide-placeholder required />
                    <x-common.input name="description" id="c-desc" :label="__('savings::savings.description')" required />
                    <x-common.input name="amount" id="c-amt" :label="__('savings::savings.amount')" required />
                    <x-common.input name="due_date" id="c-due" type="date" :label="__('savings::savings.due_date')" />
                    <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                </form>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-parts]');
                if (!form) return;
                var rows = form.querySelector('[data-part-rows]'), next = 1;
                form.querySelector('[data-add-part]').addEventListener('click', function () {
                    rows.insertAdjacentHTML('beforeend', '<div class="grid grid-cols-2 gap-2"><input type="date" name="parts[' + next + '][date]" class="rounded-lg border border-line px-2 py-1.5 text-sm" required><input type="text" name="parts[' + next + '][amount]" class="rounded-lg border border-line px-2 py-1.5 text-sm" required></div>');
                    next++;
                });
            })();
        </script>
    @endpush
</x-layouts.app>
