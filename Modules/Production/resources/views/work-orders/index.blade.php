<x-layouts.app :title="__('production::production.work_orders')">
    <x-common.page-header :title="__('production::production.work_orders')" :description="__('production::production.work_orders_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create production.work_order'))
                <x-common.button href="{{ route('production.work-orders.create') }}" size="sm">+ {{ __('production::production.add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl p-5 mb-4">
        <h2 class="font-display font-bold text-ink mb-3">{{ __('production::production.today') }}</h2>
        @forelse ($today as $row)
            <div class="flex items-center justify-between gap-3 py-1.5 text-sm">
                <span class="font-medium text-ink">{{ $row['line'] }}</span>
                <span class="{{ $row['target'] && $row['done'] < $row['target'] ? 'text-warning' : 'text-success' }} font-semibold">
                    @include('inventory::partials.qty', ['value' => $row['done']])
                    @if ($row['target'])<span class="text-muted font-normal">· {{ __('production::production.target', ['target' => \Modules\Inventory\Exceptions\InsufficientStock::format($row['target'])]) }}</span>@endif
                </span>
            </div>
        @empty
            <p class="text-sm text-muted">{{ __('production::production.today_none') }}</p>
        @endforelse
    </div>

    <x-common.filter-panel :action="route('production.work-orders.index')" :open="array_filter(\Illuminate\Support\Arr::except($filters, 'status')) !== [] || ($filters['status'] ?? 'open') !== 'open'">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('production::production.search_placeholder')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? 'open'" compact hide-placeholder
            :options="['open' => __('production::production.open'), '' => __('body.all')] + collect(\Modules\Production\Enums\WorkOrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        <x-common.select name="line" :label="__('production::production.line')" :value="$filters['line'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + array_combine($lines, $lines)" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('production::production.work_order') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('production::production.product') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('production::production.line') }}</th>
                    <th class="px-5 py-3 font-semibold min-w-[12rem]">{{ __('production::production.progress') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('production::production.due_date') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($workOrders as $wo)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 whitespace-nowrap"><a href="{{ route('production.work-orders.show', $wo) }}" class="font-medium text-ink hover:text-brand">{{ $wo->number }}</a></td>
                        <td class="px-5 py-3.5">{{ $wo->variant?->displayName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $wo->line ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 rounded-full bg-surface overflow-hidden"><div class="h-full bg-brand" style="width: {{ $wo->progressPercent() }}%"></div></div>
                                <span class="text-xs text-muted whitespace-nowrap">@include('inventory::partials.qty', ['value' => $wo->finished()]) / @include('inventory::partials.qty', ['value' => $wo->quantity])</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $wo->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$wo->status->color()">{{ $wo->status->label() }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('production::production.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$workOrders" />
    </div>
</x-layouts.app>
