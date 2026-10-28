<div class="bg-white border border-line rounded-xl p-5 space-y-3">
    <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.wo_panel', ['code' => $style->code]) }}</h2>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
            <th class="px-3 py-2 font-semibold">{{ __('garmentpack::garment.material') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.needed') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.issued') }}</th>
            <th class="px-3 py-2 font-semibold text-right">{{ __('garmentpack::garment.remaining') }}</th>
        </tr></thead>
        <tbody class="divide-y divide-line">
            @foreach ($rows as $row)
                <tr>
                    <td class="px-3 py-2.5">{{ $row['material']->variant?->displayName() }}</td>
                    <td class="px-3 py-2.5 text-right">@include('inventory::partials.qty', ['value' => $row['needed']]) {{ $row['material']->variant?->item?->unit }}</td>
                    <td class="px-3 py-2.5 text-right">@include('inventory::partials.qty', ['value' => $row['issued']])</td>
                    <td class="px-3 py-2.5 text-right font-semibold {{ $row['remaining'] > 0 ? 'text-warning' : 'text-success' }}">@include('inventory::partials.qty', ['value' => $row['remaining']])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if (collect($rows)->sum('remaining') > 0 && $workOrder->status->isOpen() && \App\Helpers\AuthHelper::can('create production.entry') && \App\Helpers\AuthHelper::can('create inventory.movement'))
        <form action="{{ route('garmentpack.work-orders.issue', $workOrder) }}" method="POST" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="w-56"><x-common.select name="store" :label="__('garmentpack::garment.issue_from')" :options="$stores" hide-placeholder required /></div>
            <x-common.button type="submit" size="sm">{{ __('garmentpack::garment.issue_remaining') }}</x-common.button>
        </form>
    @endif
</div>

@php
    $can = fn ($p) => \App\Helpers\AuthHelper::can($p);
    $open = $workOrder->status->isOpen();
    $stageOptions = collect($workOrder->stages)->mapWithKeys(fn ($s, $i) => [$i => ($i + 1).'. '.$s])->all();
@endphp
<div class="bg-white border border-line rounded-xl p-5 space-y-4">
    <div>
        <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.piece_work') }}</h2>
        <p class="text-xs text-muted">{{ __('garmentpack::garment.piece_work_hint') }}</p>
    </div>
    @foreach ($pieceWork as $work)
        <div class="flex justify-between text-sm border-b border-line pb-1.5">
            <span>{{ $work->worker?->name }} · {{ $work->stage_name }} · {{ $work->work_date->format('d/m') }}</span>
            <span>@include('inventory::partials.qty', ['value' => $work->quantity]) · {{ money($work->amount) }}</span>
        </div>
    @endforeach
    @if ($open && $workers && $can('create production.entry'))
        <form action="{{ route('garmentpack.work-orders.piece-work', $workOrder) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            @csrf
            <x-common.select name="worker" id="pw-worker" :label="__('garmentpack::garment.worker')" :options="$workers" hide-placeholder required compact />
            <x-common.select name="stage" id="pw-stage" :label="__('garmentpack::garment.stage')" :options="$stageOptions" hide-placeholder required compact />
            <x-common.input name="quantity" id="pw-qty" type="number" step="any" min="0" :label="__('garmentpack::garment.quantity')" required compact />
            <x-common.input name="work_date" id="pw-date" type="date" :label="__('garmentpack::garment.date')" :value="now()->toDateString()" compact />
            <x-common.button type="submit" size="sm">{{ __('garmentpack::garment.piece_work_add') }}</x-common.button>
        </form>
    @endif
</div>

<div class="bg-white border border-line rounded-xl p-5 space-y-4">
    <div>
        <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.cmt') }}</h2>
        <p class="text-xs text-muted">{{ __('garmentpack::garment.cmt_hint') }}</p>
    </div>
    @foreach ($cmtJobs as $job)
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-line pb-2">
            <span>{{ $job->number }} · {{ $job->subcontractor }} · {{ $job->stage_name }} · {{ __('garmentpack::garment.sent') }} @include('inventory::partials.qty', ['value' => $job->sent_quantity]) / {{ __('garmentpack::garment.returned') }} @include('inventory::partials.qty', ['value' => $job->returned_quantity])</span>
            @if ($job->outstanding() > 0 && $open && $can('update garments.cmt'))
                <form action="{{ route('garmentpack.cmt.return', $job) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <input type="number" step="any" min="0" name="quantity" value="{{ $job->outstanding() }}" class="w-20 rounded-lg border border-line px-2 py-1 text-right text-sm">
                    <button type="submit" class="text-xs font-semibold text-brand">{{ __('garmentpack::garment.return') }}</button>
                </form>
            @endif
        </div>
    @endforeach
    @if ($open && count($workOrder->stages) > 1 && $can('create garments.cmt'))
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('garmentpack::garment.cmt_send') }}</summary>
            <form action="{{ route('garmentpack.work-orders.cmt', $workOrder) }}" method="POST" class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <x-common.input name="subcontractor" id="cmt-name" :label="__('garmentpack::garment.subcontractor')" required compact />
                <x-common.input name="phone" id="cmt-phone" :label="__('garmentpack::garment.phone')" compact />
                <x-common.select name="stage" id="cmt-stage" :label="__('garmentpack::garment.stage')" :options="collect($stageOptions)->except(0)->all()" hide-placeholder required compact />
                <x-common.input name="sent_quantity" id="cmt-qty" type="number" step="any" min="0" :label="__('garmentpack::garment.quantity')" required compact />
                <x-common.input name="rate" id="cmt-rate" type="number" step="any" min="0" :label="__('garmentpack::garment.rate')" compact />
                <x-common.input name="sent_on" id="cmt-sent" type="date" :label="__('garmentpack::garment.sent_on')" :value="now()->toDateString()" required compact />
                <x-common.input name="due_on" id="cmt-due" type="date" :label="__('garmentpack::garment.due_on')" compact />
                <div class="sm:col-span-2 flex items-end"><x-common.button type="submit" size="sm">{{ __('garmentpack::garment.cmt_send') }}</x-common.button></div>
            </form>
        </details>
    @endif
</div>

<div class="bg-white border border-line rounded-xl p-5 space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.bundles') }}</h2>
            <p class="text-xs text-muted">{{ __('garmentpack::garment.bundles_hint') }}</p>
        </div>
        @if ($bundles->isNotEmpty())
            <a href="{{ route('garmentpack.work-orders.tickets', $workOrder) }}" target="_blank" class="text-sm font-semibold text-brand">{{ __('garmentpack::garment.bundles_tickets') }} →</a>
        @endif
    </div>
    @if ($bundles->isNotEmpty())
        <div class="flex flex-wrap gap-1.5">
            @foreach ($bundles as $bundle)
                <span class="rounded-lg bg-surface px-2 py-1 text-xs">#{{ $bundle->number }} · @include('inventory::partials.qty', ['value' => $bundle->quantity])</span>
            @endforeach
        </div>
    @endif
    @if ($open && $can('create production.entry'))
        <form action="{{ route('garmentpack.work-orders.bundles', $workOrder) }}" method="POST" class="flex items-end gap-3">
            @csrf
            <div class="w-40"><x-common.input name="size" id="bundle-size" type="number" step="any" min="1" :label="__('garmentpack::garment.bundle_size')" value="20" compact /></div>
            <x-common.button type="submit" size="sm" variant="secondary">{{ __('garmentpack::garment.bundles_make') }}</x-common.button>
        </form>
    @endif
</div>
