<div class="overflow-x-auto">
<table class="w-full text-sm">
    <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
        <th class="px-5 py-2.5 font-semibold">{{ __('savings::savings.date') }}</th>
        <th class="px-5 py-2.5 font-semibold">{{ __('savings::savings.description') }}</th>
        <th class="px-5 py-2.5 font-semibold">{{ __('savings::savings.account') }}</th>
        <th class="px-5 py-2.5 font-semibold">{{ __('savings::savings.category') }}</th>
        <th class="px-5 py-2.5 font-semibold text-right">{{ __('savings::savings.amount') }}</th>
    </tr></thead>
    <tbody class="divide-y divide-line">
        @forelse ($transactions as $t)
            @php $net = (int) $t->entries->sum('amount'); @endphp
            <tr class="{{ $t->isReversed() || $t->isReversal() ? 'opacity-60' : '' }}">
                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $t->effective_date->format('d/m/Y') }}</td>
                <td class="px-5 py-3">
                    <a href="{{ route('savings.transactions.show', $t) }}" class="font-medium text-ink hover:text-brand">{{ $t->description }}</a>
                    <span class="block text-xs text-muted">{{ __('savings::savings.type_'.$t->type) }}{{ $t->counterparty ? ' · '.$t->counterparty : '' }}
                        @if ($t->isReversed()) · <b>{{ __('savings::savings.reversed') }}</b>@endif
                        @if ($t->isReversal()) · <b>{{ __('savings::savings.reversal') }}</b>@endif</span>
                </td>
                <td class="px-5 py-3 whitespace-nowrap">{{ $t->entries->map(fn ($e) => $e->account?->name)->implode(' → ') }}</td>
                <td class="px-5 py-3">{{ $t->lines->map(fn ($l) => $l->category?->name ?? __('savings::savings.nature_'.$l->nature))->unique()->implode(', ') ?: '—' }}</td>
                <td class="px-5 py-3 text-right font-semibold">
                    @if ($t->type === 'transfer')
                        <span class="text-muted">{{ \Modules\Savings\Support\Money::format($t->amount, $t->currency) }}</span>
                    @else
                        @include('savings::partials.money', ['v' => $net, 'c' => $t->entries->first()?->account?->currency, 'signed' => true])
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }}</td></tr>
        @endforelse
    </tbody>
</table>
</div>
