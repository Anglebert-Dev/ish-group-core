@php $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $currency); $editable = \App\Helpers\AuthHelper::can('update savings.budget'); @endphp
<x-layouts.app :title="__('savings::savings.budgets')">
    <x-common.page-header :title="__('savings::savings.budgets')" :description="$label">
        <x-slot:actions>
            <x-common.button href="{{ route('savings.budgets.index', ['date' => $previous]) }}" variant="secondary" size="sm">← {{ __('savings::savings.previous') }}</x-common.button>
            <x-common.button href="{{ route('savings.budgets.index', ['date' => $next]) }}" variant="secondary" size="sm">{{ __('savings::savings.next') }} →</x-common.button>
        </x-slot:actions>
    </x-common.page-header>
    <p class="text-sm text-muted mb-4">{{ __('savings::savings.budget_hint') }}</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        @foreach (['income' => $income, 'budgeted' => $rows->sum('budgeted'), 'spent' => $rows->sum('spent'), 'remaining' => $rows->sum('budgeted') - $rows->whereNotNull('budgeted')->sum('spent')] as $label => $value)
            <div class="bg-white border border-line rounded-xl p-4"><p class="text-xs uppercase text-muted font-semibold">{{ __('savings::savings.'.$label) }}</p><p class="text-lg font-display font-bold {{ $value < 0 ? 'text-danger' : 'text-ink' }}">{{ $m($value) }}</p></div>
        @endforeach
    </div>

    <form action="{{ route('savings.budgets.update') }}" method="POST" class="bg-white border border-line rounded-xl overflow-hidden">
        @csrf @method('PUT')
        <input type="hidden" name="date" value="{{ $period[0]->toDateString() }}">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs text-muted uppercase border-b border-line bg-surface/60">
                <th class="px-5 py-3">{{ __('savings::savings.category') }}</th><th class="px-5 py-3">{{ __('savings::savings.budgeted') }}</th>
                <th class="px-5 py-3 text-right">{{ __('savings::savings.spent') }}</th><th class="px-5 py-3 text-right">{{ __('savings::savings.remaining') }}</th>
                <th class="px-5 py-3 text-right">{{ __('savings::savings.projected') }}</th><th class="px-5 py-3 w-40"></th>
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-5 py-3 {{ $row['category']->parent_id ? 'pl-10 text-muted' : 'font-medium text-ink' }}">{{ $row['category']->name }}</td>
                        <td class="px-5 py-2">
                            @if ($editable)
                                <input type="text" inputmode="decimal" name="limits[{{ $row['category']->uuid }}]" value="{{ $row['budgeted'] !== null ? \Modules\Savings\Support\Money::toDecimal($row['budgeted'], $currency) : '' }}" placeholder="{{ __('savings::savings.no_limit') }}" class="w-32 rounded-lg border border-line px-2 py-1.5 text-sm">
                            @else
                                {{ $row['budgeted'] !== null ? $m($row['budgeted']) : '—' }}
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">{{ $m($row['spent']) }}</td>
                        <td class="px-5 py-3 text-right font-semibold {{ ($row['remaining'] ?? 0) < 0 ? 'text-danger' : '' }}">{{ $row['remaining'] !== null ? $m($row['remaining']) : '—' }}</td>
                        <td class="px-5 py-3 text-right {{ $row['budgeted'] !== null && $row['projected'] > $row['budgeted'] ? 'text-warning font-semibold' : 'text-muted' }}">{{ $m($row['projected']) }}</td>
                        <td class="px-5 py-3">@if ($row['percent'] !== null)<div class="h-1.5 rounded-full bg-surface overflow-hidden"><div class="h-full {{ $row['percent'] >= 100 ? 'bg-danger' : ($row['percent'] >= 80 ? 'bg-warning' : 'bg-brand') }}" style="width: {{ min(100, $row['percent']) }}%"></div></div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-muted">{{ __('savings::savings.none') }} <a class="text-brand font-semibold" href="{{ route('savings.categories.index') }}">{{ __('savings::savings.categories') }} →</a></td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if ($editable && $rows->isNotEmpty())
            <div class="px-5 py-4 border-t border-line flex flex-wrap gap-3">
                <x-common.button type="submit" size="sm">{{ __('savings::savings.set_limits') }}</x-common.button>
            </div>
        @endif
    </form>
    @if ($editable)
        <form action="{{ route('savings.budgets.copy') }}" method="POST" class="mt-3">
            @csrf <input type="hidden" name="date" value="{{ $period[0]->toDateString() }}">
            <x-common.button type="submit" variant="secondary" size="sm">{{ __('savings::savings.copy_previous') }}</x-common.button>
        </form>
    @endif
</x-layouts.app>
