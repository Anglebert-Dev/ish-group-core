@php
    $can = fn (string $permission) => \App\Helpers\AuthHelper::can($permission);
    $variants = $item->variants->where('is_active', true)->values();
    $variantOptions = $variants->mapWithKeys(fn ($v) => [$v->uuid => $v->optionLabel() ?: $item->name])->all();
    $storeOptions = $activeStores->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all();
    $action = old('action', 'receive');
@endphp
<x-layouts.app :title="$item->name">
    <x-common.page-header :title="$item->name" :description="$item->type->label().($item->category ? ' · '.$item->category : '').($item->sku ? ' · '.$item->sku : '')">
        <x-slot:actions>
            <x-common.button href="{{ route('inventory.items.index') }}" variant="secondary" size="sm">← {{ __('inventory::inventory.items') }}</x-common.button>
            @if ($can('update inventory.item'))
                <x-common.button href="{{ route('inventory.items.edit', $item) }}" size="sm">{{ __('body.edit') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border border-line rounded-xl p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('inventory::inventory.on_hand') }}</p>
                    <p class="text-2xl font-display font-bold text-ink mt-1">@include('inventory::partials.qty', ['value' => $onHand]) <span class="text-sm font-normal text-muted">{{ $item->unit }}</span></p>
                </div>
                <div class="bg-white border border-line rounded-xl p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('inventory::inventory.variants') }}</p>
                    <p class="text-2xl font-display font-bold text-ink mt-1">{{ $variants->count() }}</p>
                </div>
                <div class="bg-white border border-line rounded-xl p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('inventory::inventory.reorder_level') }}</p>
                    <p class="text-2xl font-display font-bold text-ink mt-1">{{ $item->reorder_level !== null ? \Modules\Inventory\Exceptions\InsufficientStock::format($item->reorder_level) : '—' }}</p>
                </div>
                <div class="bg-white border border-line rounded-xl p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('body.status') }}</p>
                    <p class="mt-2"><x-common.badge :color="$item->is_active ? 'success' : 'muted'">{{ $item->is_active ? __('inventory::inventory.status_active') : __('inventory::inventory.status_inactive') }}</x-common.badge></p>
                </div>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line">
                    <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.stock_by_store') }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                            <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.variant') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.sale_price') }}</th>
                            @foreach ($stores as $store)
                                <th class="px-5 py-3 font-semibold text-right whitespace-nowrap">{{ $store->name }}</th>
                            @endforeach
                            <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.on_hand') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($variants as $variant)
                            @php
                                $levels = $variant->stockLevels->pluck('quantity', 'store_id');
                                $total = (float) $levels->sum();
                                $reorder = $variant->reorderLevel();
                            @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-ink">{{ $variant->optionLabel() ?: $item->name }}</p>
                                    @if ($variant->sku)<p class="text-xs text-muted">{{ $variant->sku }}</p>@endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">{{ $variant->sale_price !== null ? number_format((float) $variant->sale_price, 2) : '—' }}</td>
                                @foreach ($stores as $store)
                                    <td class="px-5 py-3 text-right">@include('inventory::partials.qty', ['value' => $levels[$store->id] ?? 0])</td>
                                @endforeach
                                <td class="px-5 py-3 text-right whitespace-nowrap font-semibold {{ $total <= 0 ? 'text-danger' : ($reorder !== null && $total <= $reorder ? 'text-warning' : 'text-ink') }}">
                                    @include('inventory::partials.qty', ['value' => $total])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            <div class="bg-white border border-line rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
                    <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.recent_movements') }}</h2>
                    @if ($can('list inventory.movement'))
                        <a href="{{ route('inventory.movements.index', ['item' => $item->uuid]) }}" class="text-sm font-semibold text-brand hover:text-brand-dark">{{ __('inventory::inventory.all_movements') }} →</a>
                    @endif
                </div>
                @include('inventory::movements.table', ['movements' => $movements, 'showItem' => false])
            </div>
        </div>

        <div class="space-y-4">
            @if ($item->is_active && $can('create inventory.movement') && $variants->isNotEmpty() && $activeStores->isNotEmpty())
                <form action="{{ route('inventory.items.stock', $item) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4" data-stock-form>
                    @csrf
                    <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.stock_action') }}</h2>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['receive', 'issue', 'adjust', 'transfer'] as $choice)
                            <label class="cursor-pointer">
                                <input type="radio" name="action" value="{{ $choice }}" class="peer sr-only" @checked($action === $choice)>
                                <span class="block text-center rounded-lg border border-line px-3 py-2 text-sm font-semibold text-body peer-checked:border-brand peer-checked:bg-brand-light peer-checked:text-brand">{{ __('inventory::inventory.action_'.$choice) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @foreach (['receive', 'issue', 'adjust', 'transfer'] as $choice)
                        <p class="text-xs text-muted {{ $action === $choice ? '' : 'hidden' }}" data-hint="{{ $choice }}">{{ __('inventory::inventory.action_'.$choice.'_hint') }}</p>
                    @endforeach

                    @if ($item->hasOptions())
                        <x-common.select name="variant" :label="__('inventory::inventory.variant')" :value="old('variant')" :options="$variantOptions" required />
                    @else
                        <input type="hidden" name="variant" value="{{ $variants->first()->uuid }}">
                    @endif
                    <x-common.select name="store" :label="__('inventory::inventory.store')" :value="old('store', $activeStores->firstWhere('is_default', true)?->uuid)" :options="$storeOptions" hide-placeholder required />
                    <div data-for="transfer" class="{{ $action === 'transfer' ? '' : 'hidden' }}">
                        <x-common.select name="to_store" :label="__('inventory::inventory.to_store')" :value="old('to_store')" :options="$storeOptions" />
                    </div>
                    <div data-label-for="quantity">
                        <x-common.input name="quantity" type="number" step="any" min="0" :label="$action === 'adjust' ? __('inventory::inventory.counted_quantity') : __('inventory::inventory.quantity').' ('.$item->unit.')'" :value="old('quantity')" required />
                    </div>
                    <div data-for="receive" class="{{ $action === 'receive' ? '' : 'hidden' }}">
                        <x-common.input name="unit_cost" type="number" step="any" min="0" :label="__('inventory::inventory.unit_cost')" :value="old('unit_cost')" />
                    </div>
                    <x-common.input name="reason" :label="__('inventory::inventory.reason')" :value="old('reason')" :hint="__('inventory::inventory.reason_required_hint')" />
                    <x-common.button type="submit" class="w-full">{{ __('body.save') }}</x-common.button>
                </form>
                @push('scripts')
                    <script>
                        (function () {
                            var form = document.querySelector('[data-stock-form]');
                            if (!form) return;
                            var labels = {
                                adjust: @json(__('inventory::inventory.counted_quantity')),
                                other: @json(__('inventory::inventory.quantity').' ('.$item->unit.')')
                            };
                            function update() {
                                var action = form.querySelector('input[name="action"]:checked').value;
                                form.querySelectorAll('[data-hint]').forEach(function (el) { el.classList.toggle('hidden', el.dataset.hint !== action); });
                                form.querySelectorAll('[data-for]').forEach(function (el) { el.classList.toggle('hidden', el.dataset.for !== action); });
                                var label = form.querySelector('[data-label-for="quantity"] label');
                                if (label) label.firstChild.textContent = (action === 'adjust' ? labels.adjust : labels.other) + ' ';
                            }
                            form.querySelectorAll('input[name="action"]').forEach(function (el) { el.addEventListener('change', update); });
                        })();
                    </script>
                @endpush
            @endif

            @if ($item->description)
                <div class="bg-white border border-line rounded-xl p-5">
                    <h2 class="font-display font-bold text-ink mb-2">{{ __('body.description') }}</h2>
                    <p class="text-sm text-body leading-relaxed">{!! nl2br(e($item->description)) !!}</p>
                </div>
            @endif

            @if ($can('update inventory.item'))
                <div class="bg-white border border-line rounded-xl p-5 space-y-3">
                    <h2 class="font-display font-bold text-ink">{{ __('body.status') }}</h2>
                    <x-common.action-button :action="route('inventory.items.status', $item)" method="PUT" :color="$item->is_active ? 'warning' : 'success'">
                        <x-slot:fields><input type="hidden" name="active" value="{{ $item->is_active ? 0 : 1 }}"></x-slot:fields>
                        {{ $item->is_active ? __('inventory::inventory.item_deactivate') : __('inventory::inventory.item_activate') }}
                    </x-common.action-button>
                </div>
            @endif

            @if ($can('delete inventory.item') && $movements->isEmpty())
                <form action="{{ route('inventory.items.destroy', $item) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-3">
                    @csrf @method('DELETE')
                    <h2 class="font-display font-bold text-danger">{{ __('body.delete') }}</h2>
                    <x-common.button type="submit" variant="secondary" size="sm"
                        data-confirm data-confirm-title="{{ __('body.delete') }} {{ $item->name }}?" data-confirm-variant="danger">{{ __('inventory::inventory.item_delete') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
