<x-layouts.app :title="__('savings::savings.forecast')">
    <x-common.page-header :title="__('savings::savings.forecast')" :description="__('savings::savings.forecast_description')" />
    <form method="GET" class="bg-white border border-line rounded-xl p-4 mb-4 flex flex-wrap items-end gap-3" data-forecast>
        <div class="w-56"><x-common.select name="account" :label="__('savings::savings.account')" :value="$account" :options="['' => __('savings::savings.liquid')] + $accounts->mapWithKeys(fn ($a) => [$a->uuid => $a->name])->all()" hide-placeholder compact /></div>
        <div class="w-32"><x-common.input name="days" type="number" min="7" max="365" :label="__('savings::savings.days')" :value="$days" compact /></div>
        <x-common.button type="submit" size="sm">{{ __('savings::savings.show') }}</x-common.button>
        <p class="text-xs text-muted w-full">{{ __('savings::savings.what_if_hint') }}</p>

        @foreach ($rows as $row)
            @php $m = fn ($v) => \Modules\Savings\Support\Money::format((int) $v, $row['account']->currency); @endphp
            <div class="w-full bg-surface/40 border border-line rounded-xl p-4 space-y-3">
                <div class="flex flex-wrap justify-between gap-3">
                    <h2 class="font-display font-bold text-ink">{{ $row['account']->name }}</h2>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <span>{{ __('savings::savings.actual') }}: <b>{{ $m($row['start']) }}</b></span>
                        <span>{{ __('savings::savings.lowest') }}: <b class="{{ $row['lowest'] < $row['threshold'] ? 'text-danger' : '' }}">{{ $m($row['lowest']) }}</b> ({{ \Illuminate\Support\Carbon::parse($row['lowest_date'])->format('d/m') }})</span>
                        <span>{{ __('savings::savings.end_balance', ['days' => $days]) }}: <b>{{ $m($row['end']) }}</b></span>
                    </div>
                </div>
                @if ($row['shortfall'])
                    <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm">
                        <p class="font-semibold text-danger">{{ __('savings::savings.shortfall', ['date' => \Illuminate\Support\Carbon::parse($row['shortfall'])->format('d/m/Y')]) }}{{ $row['threshold'] ? ' ('.__('savings::savings.minimum_balance').' '.$m($row['threshold']).')' : '' }}</p>
                        <p class="text-xs text-muted mt-1">{{ __('savings::savings.shortfall_causes') }}:</p>
                        @foreach ($row['causes'] as $c)<p class="text-xs">• {{ \Illuminate\Support\Carbon::parse($c['date'])->format('d/m') }} {{ $c['label'] }} ({{ $m(-$c['effects'][$row['account']->id]) }})</p>@endforeach
                    </div>
                @else
                    <p class="text-sm text-success">{{ __('savings::savings.no_shortfall') }}</p>
                @endif

                @php $points = collect($row['days']); $max = max(1, $points->max()); $min = min(0, $points->min()); $span = max(1, $max - $min); @endphp
                <svg viewBox="0 0 {{ max(1, $points->count() - 1) }} 100" preserveAspectRatio="none" class="w-full h-24">
                    <line x1="0" x2="{{ $points->count() }}" y1="{{ 100 - ($row['threshold'] - $min) / $span * 100 }}" y2="{{ 100 - ($row['threshold'] - $min) / $span * 100 }}" stroke="#DC2626" stroke-width="0.6" stroke-dasharray="2 2" vector-effect="non-scaling-stroke" />
                    <polyline fill="none" stroke="currentColor" class="text-brand" stroke-width="2" vector-effect="non-scaling-stroke"
                        points="{{ $points->values()->map(fn ($v, $i) => $i.','.round(100 - ($v - $min) / $span * 100, 2))->implode(' ') }}" />
                </svg>

                <table class="w-full text-sm">
                    <tbody class="divide-y divide-line">
                        @foreach ($row['events'] as $e)
                            @php $effect = $e['effects'][$row['account']->id]; $isEvent = ! str_starts_with($e['key'], 'commitment:'); @endphp
                            <tr>
                                <td class="py-2 pr-2 whitespace-nowrap text-muted">{{ \Illuminate\Support\Carbon::parse($e['date'])->format('d/m') }}</td>
                                <td class="py-2 pr-2">{{ $e['label'] }} <x-common.badge :color="match ($e['status']) { 'late' => 'danger', 'uncertain' => 'warning', 'committed' => 'muted', default => 'brand' }">{{ __('savings::savings.status_'.(in_array($e['status'], ['late', 'committed']) ? $e['status'] : 'expected')) }} · {{ in_array($e['status'], ['confirmed', 'expected', 'uncertain']) ? __('savings::savings.confidence_'.$e['status']) : '' }}</x-common.badge>@if ($e['changed'])<x-common.badge color="warning">{{ __('savings::savings.changed') }}</x-common.badge>@endif</td>
                                <td class="py-2 pr-2 text-right">@include('savings::partials.money', ['v' => $effect, 'c' => $row['account']->currency, 'signed' => true])</td>
                                @if ($isEvent)
                                    <td class="py-2 text-right whitespace-nowrap">
                                        <input type="date" name="w[{{ $e['key'] }}][date]" value="{{ $whatIf[$e['key']]['date'] ?? '' }}" class="rounded border border-line px-1 py-0.5 text-xs">
                                        <input type="text" name="w[{{ $e['key'] }}][amount]" value="{{ $whatIf[$e['key']]['amount'] ?? '' }}" placeholder="{{ __('savings::savings.amount') }}" class="w-20 rounded border border-line px-1 py-0.5 text-xs">
                                        <label class="text-xs"><input type="checkbox" name="w[{{ $e['key'] }}][exclude]" value="1" @checked(! empty($whatIf[$e['key']]['exclude']))> {{ __('savings::savings.exclude') }}</label>
                                    </td>
                                @else
                                    <td></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </form>
</x-layouts.app>
