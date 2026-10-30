@php $m = fn ($v, $c) => \Modules\Savings\Support\Money::format((int) $v, $c); @endphp
<x-layouts.app :title="__('savings::savings.obligations')">
    <x-common.page-header :title="__('savings::savings.obligations')" />
    <div class="flex flex-wrap gap-2 mb-4">
        @foreach (['' => __('body.all'), 'receivable' => __('savings::savings.direction_receivable'), 'payable' => __('savings::savings.direction_payable'), 'restore' => __('savings::savings.direction_restore')] as $key => $label)
            <a href="{{ route('savings.obligations.index', array_filter(['direction' => $key])) }}" class="px-4 py-2 rounded-lg text-sm font-semibold border {{ ($direction ?? '') === $key ? 'bg-brand text-white border-brand' : 'bg-white text-body border-line' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted uppercase border-b border-line bg-surface/60">
                    <th class="px-5 py-3">{{ __('savings::savings.counterparty') }}</th><th class="px-5 py-3 text-right">{{ __('savings::savings.original') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('savings::savings.outstanding') }}</th><th class="px-5 py-3">{{ __('body.status') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('savings.obligations.show', $r['o']) }}" class="font-medium text-ink hover:text-brand">{{ $r['o']->counterparty ?: $r['o']->description }}</a>
                                <span class="block text-xs text-muted">{{ __('savings::savings.direction_'.$r['o']->direction) }} · {{ $r['o']->description }}{{ $r['o']->due_date ? ' · '.$r['o']->due_date->format('d/m/Y') : '' }}</span></td>
                            <td class="px-5 py-3 text-right">{{ $m($r['original'], $r['o']->currency) }}</td>
                            <td class="px-5 py-3 text-right font-semibold">{{ $m($r['outstanding'], $r['o']->currency) }}</td>
                            <td class="px-5 py-3"><x-common.badge :color="match ($r['state']) { 'settled' => 'success', 'overdue', 'disputed' => 'danger', 'written_off' => 'muted', 'partly_repaid' => 'warning', default => 'brand' }">{{ __('savings::savings.state_'.$r['state']) }}</x-common.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (\App\Helpers\AuthHelper::can('create savings.obligation'))
            <form action="{{ route('savings.obligations.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-3">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('savings::savings.obligation_add') }}</h2>
                <x-common.select name="direction" :label="__('savings::savings.direction')" :value="$direction ?? 'receivable'" :options="['receivable' => __('savings::savings.direction_receivable'), 'payable' => __('savings::savings.direction_payable'), 'restore' => __('savings::savings.direction_restore')]" hide-placeholder required />
                <p class="text-xs text-muted">{{ __('savings::savings.restore_hint') }}</p>
                <x-common.input name="counterparty" :label="__('savings::savings.counterparty')" />
                <x-common.input name="description" :label="__('savings::savings.description')" required />
                <x-common.select name="account" :label="__('savings::savings.restore_account')" :options="['' => '—'] + $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all()" hide-placeholder />
                <x-common.input name="currency" :label="__('savings::savings.currency')" :value="$currency" maxlength="3" required />
                <x-common.input name="amount" :label="__('savings::savings.opening_amount')" />
                <x-common.input name="due_date" type="date" :label="__('savings::savings.due_date')" />
                <x-common.textarea name="notes" :label="__('savings::savings.evidence')" :rows="2" />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
