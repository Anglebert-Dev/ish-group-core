@php
    $can = fn (string $permission) => \App\Helpers\AuthHelper::can($permission);
    $open = $workOrder->status->isOpen();
    $unit = $workOrder->variant?->item?->unit;
@endphp
<x-layouts.app :title="$workOrder->number">
    <x-common.page-header :title="$workOrder->number" :description="$workOrder->variant?->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ route('production.work-orders.index') }}" variant="secondary" size="sm">← {{ __('production::production.work_orders') }}</x-common.button>
            @if ($open && $can('update production.work_order'))
                <x-common.button href="{{ route('production.work-orders.edit', $workOrder) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white border border-line rounded-xl p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display font-bold text-ink">{{ __('production::production.stages') }}</h2>
                    <span class="text-sm text-muted">{{ __('production::production.finished') }}: <b class="text-ink">@include('inventory::partials.qty', ['value' => $workOrder->finished()])</b> {{ __('production::production.of', ['total' => \Modules\Inventory\Exceptions\InsufficientStock::format($workOrder->quantity)]) }} {{ $unit }}</span>
                </div>
                <div class="grid gap-3" style="grid-template-columns: repeat({{ count($workOrder->stages) }}, minmax(0, 1fr));">
                    @foreach ($workOrder->stages as $i => $stage)
                        @php $count = $workOrder->count($i); $pct = $workOrder->quantity > 0 ? min(100, round($count / $workOrder->quantity * 100)) : 0; @endphp
                        <div class="rounded-xl border border-line p-3 {{ $i === $workOrder->lastStage() ? 'bg-brand-light/60' : 'bg-surface' }}">
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted truncate">{{ $i + 1 }}. {{ $stage }}</p>
                            <p class="text-xl font-display font-bold text-ink mt-1">@include('inventory::partials.qty', ['value' => $count])</p>
                            <div class="h-1.5 rounded-full bg-white overflow-hidden mt-2"><div class="h-full bg-brand" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-common.info-card :title="__('production::production.work_order')" :rows="[
                    [__('production::production.line'), $workOrder->line],
                    [__('production::production.daily_target'), $workOrder->daily_target !== null ? \Modules\Inventory\Exceptions\InsufficientStock::format($workOrder->daily_target) : null],
                    [__('production::production.start_date'), $workOrder->start_date?->format('d/m/Y')],
                    [__('production::production.due_date'), $workOrder->due_date?->format('d/m/Y')],
                ]" />
                <x-common.info-card :title="__('production::production.product')" :rows="[
                    [__('production::production.product'), $workOrder->variant?->displayName()],
                    [__('production::production.store'), $workOrder->store?->name],
                    [__('production::production.sales_order'), $salesOrder?->number],
                ]" />
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line"><h2 class="font-display font-bold text-ink">{{ __('production::production.history') }}</h2></div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                            <th class="px-5 py-3 font-semibold">{{ __('production::production.work_date') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('production::production.stage') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.quantity') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('production::production.after') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('production::production.note') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('production::production.by') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($workOrder->entries as $entry)
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $entry->work_date->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">{{ $entry->stage_name }}</td>
                                <td class="px-5 py-3 text-right font-semibold {{ $entry->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $entry->quantity > 0 ? '+' : '' }}@include('inventory::partials.qty', ['value' => $entry->quantity])</td>
                                <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $entry->count_after])</td>
                                <td class="px-5 py-3 text-muted">{{ $entry->note ?: '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $entry->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-muted">{{ __('production::production.history_none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            {{-- Modules add panels here (e.g. GarmentPack's bill of materials) --}}
            @foreach ($panels as $panel)
                @if (filled($panel)) {!! $panel !!} @endif
            @endforeach

            <div class="bg-white border border-line rounded-xl p-5 space-y-4">
                <div>
                    <h2 class="font-display font-bold text-ink">{{ __('production::production.materials') }}</h2>
                    <p class="text-sm text-muted">{{ __('production::production.materials_hint') }}</p>
                </div>
                @forelse ($materials as $movement)
                    <div class="flex items-center justify-between text-sm border-b border-line pb-2">
                        <span>{{ $movement->variant?->displayName() }} <span class="text-muted">· {{ $movement->store?->name }} · @datetime($movement->created_at)</span></span>
                        <span class="font-semibold">@include('inventory::partials.qty', ['value' => abs($movement->quantity)]) {{ $movement->variant?->item?->unit }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('production::production.materials_none') }}</p>
                @endforelse

                @if ($workOrder->status !== \Modules\Production\Enums\WorkOrderStatus::Cancelled && $can('create production.entry') && $can('create inventory.movement') && $materialOptions)
                    <details>
                        <summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('production::production.materials_issue') }}</summary>
                        <form action="{{ route('production.work-orders.materials', $workOrder) }}" method="POST" class="mt-4 space-y-4">
                            @csrf
                            <x-common.select name="store" :label="__('production::production.materials_from')" :options="$stores" hide-placeholder required />
                            @include('inventory::partials.line-editor', ['lines' => [], 'options' => $materialOptions, 'name' => 'materials'])
                            <x-common.input name="note" :label="__('production::production.note')" />
                            <x-common.button type="submit" size="sm">{{ __('production::production.materials_issue') }}</x-common.button>
                        </form>
                    </details>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.badge :color="$workOrder->status->color()">{{ $workOrder->status->label() }}</x-common.badge>
                </div>
                @if ($salesOrder)
                    <a href="{{ route('salesorders.orders.show', $salesOrder) }}" class="text-sm font-semibold text-brand">{{ $salesOrder->number }} →</a>
                @endif
            </div>

            @if ($open && $can('create production.entry'))
                <form action="{{ route('production.work-orders.record', $workOrder) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('production::production.record') }}</h2>
                    <p class="text-xs text-muted">{{ __('production::production.record_hint') }}</p>
                    <x-common.select name="stage" :label="__('production::production.stage')" :value="old('stage')" hide-placeholder required
                        :options="collect($workOrder->stages)->mapWithKeys(fn ($s, $i) => [$i => ($i + 1).'. '.$s.' ('.\Modules\Inventory\Exceptions\InsufficientStock::format($workOrder->count($i)).')'])->all()" />
                    <x-common.input name="quantity" type="number" step="any" :label="__('inventory::inventory.quantity')" :value="old('quantity')" required />
                    <x-common.input name="work_date" type="date" :label="__('production::production.work_date')" :value="old('work_date', now()->toDateString())" />
                    <x-common.input name="note" :label="__('production::production.note')" :value="old('note')" />
                    <x-common.button type="submit" class="w-full">{{ __('production::production.record') }}</x-common.button>
                </form>
            @endif

            @if ($open && $can('approve production.work_order'))
                <form action="{{ route('production.work-orders.complete', $workOrder) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-2">
                    @csrf
                    <x-common.button type="submit" variant="secondary" size="sm" data-confirm data-confirm-title="{{ __('production::production.action_complete') }}?">{{ __('production::production.action_complete') }}</x-common.button>
                    <p class="text-xs text-muted">{{ __('production::production.action_complete_hint') }}</p>
                </form>
            @endif

            @if ($open && $can('delete production.work_order'))
                <form action="{{ route('production.work-orders.cancel', $workOrder) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf
                    <h2 class="font-display font-bold text-danger">{{ __('production::production.action_cancel') }}</h2>
                    <x-common.input name="reason" :label="__('production::production.cancel_reason')" required />
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('production::production.action_cancel') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
