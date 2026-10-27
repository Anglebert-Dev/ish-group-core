@php
    $isNew = ! $item->exists;
    $rows = old('variants', $item->exists
        ? $item->variants->where('is_active', true)->map(fn ($v) => [
            'id' => $v->id, 'option1' => $v->option1, 'option2' => $v->option2, 'sku' => $v->sku,
            'cost_price' => $v->cost_price, 'sale_price' => $v->sale_price, 'reorder_level' => $v->reorder_level,
        ])->values()->all()
        : []);
    $rows = $rows ?: [[]];
    $option1 = old('option1_name', $item->option1_name);
    $option2 = old('option2_name', $item->option2_name);
    $cell = 'w-full min-w-[6rem] rounded-lg border border-line bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand';
@endphp
<x-layouts.app :title="$isNew ? __('inventory::inventory.item_add') : $item->name">
    <x-common.page-header :title="$isNew ? __('inventory::inventory.item_add') : __('inventory::inventory.item_edit')" :description="$isNew ? null : $item->name">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('inventory.items.index') : route('inventory.items.show', $item) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('inventory.items.store') : route('inventory.items.update', $item) }}" method="POST" class="space-y-6 max-w-6xl" data-item-form>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.item_details') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <x-common.select name="type" :label="__('body.type')" :value="old('type', $item->type?->value)" hide-placeholder required
                    :options="['finished' => __('inventory::inventory.type_finished'), 'material' => __('inventory::inventory.type_material')]" />
                <x-common.input name="name" :label="__('body.name')" :value="old('name', $item->name)" required />
                <x-common.input name="sku" :label="__('inventory::inventory.sku')" :value="old('sku', $item->sku)" />
                <x-common.input name="category" :label="__('inventory::inventory.category')" :value="old('category', $item->category)" :hint="__('inventory::inventory.category_hint')" list="inventory-categories" />
                <x-common.select name="unit" :label="__('inventory::inventory.unit')" :value="old('unit', $item->unit)" hide-placeholder required
                    :options="array_combine($units, $units)" />
                <x-common.input name="reorder_level" type="number" step="any" min="0" :label="__('inventory::inventory.reorder_level')" :value="old('reorder_level', $item->reorder_level)" :hint="__('inventory::inventory.reorder_level_hint')" />
            </div>
            <datalist id="inventory-categories">
                @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
            </datalist>
            <x-common.textarea name="description" :label="__('body.description')" :value="old('description', $item->description)" :rows="2" />
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.options') }}</h2>
                <p class="text-sm text-muted mt-1">{{ __('inventory::inventory.options_hint') }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-2xl">
                <x-common.input name="option1_name" :label="__('inventory::inventory.option1_name')" :value="$option1" placeholder="Size" data-option-name="1" />
                <x-common.input name="option2_name" :label="__('inventory::inventory.option2_name')" :value="$option2" placeholder="Colour" data-option-name="2" />
            </div>

            <div data-generator class="{{ filled($option1) || filled($option2) ? '' : 'hidden' }} rounded-xl border border-dashed border-line p-4 space-y-3">
                <p class="text-sm font-semibold text-ink">{{ __('inventory::inventory.variant_generate') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
                    <input type="text" data-values="1" class="{{ $cell }}" placeholder="S, M, L, XL">
                    <input type="text" data-values="2" class="{{ $cell }}" placeholder="White, Blue">
                </div>
                <p class="text-xs text-muted">{{ __('inventory::inventory.variant_generate_hint') }}</p>
                <button type="button" data-generate class="text-sm font-semibold text-brand hover:text-brand-dark">{{ __('inventory::inventory.variant_generate') }}</button>
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.pricing') }}</h2>
                <p class="text-sm text-muted mt-1" data-variants-hint>{{ __('inventory::inventory.variants_hint') }}</p>
            </div>
            @error('variants')<p class="text-sm text-danger">{{ $message }}</p>@enderror

            <div class="overflow-x-auto -mx-1">
                <table class="w-full text-sm" data-variants>
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide">
                            <th class="px-1 py-2 font-semibold" data-option-col="1">{{ $option1 ?: __('inventory::inventory.option1_name') }}</th>
                            <th class="px-1 py-2 font-semibold" data-option-col="2">{{ $option2 ?: __('inventory::inventory.option2_name') }}</th>
                            <th class="px-1 py-2 font-semibold">{{ __('inventory::inventory.sku') }}</th>
                            <th class="px-1 py-2 font-semibold">{{ __('inventory::inventory.cost_price') }}</th>
                            <th class="px-1 py-2 font-semibold">{{ __('inventory::inventory.sale_price') }}</th>
                            <th class="px-1 py-2 font-semibold">{{ __('inventory::inventory.reorder_level') }}</th>
                            @if ($isNew)
                                <th class="px-1 py-2 font-semibold" title="{{ __('inventory::inventory.opening_stock_hint', ['store' => $defaultStore->name]) }}">{{ __('inventory::inventory.opening_stock') }}</th>
                            @endif
                            <th class="px-1 py-2" data-row-tools></th>
                        </tr>
                    </thead>
                    <tbody data-variant-rows>
                        @foreach (array_values($rows) as $i => $row)
                            @include('inventory::items.variant-row', ['index' => $i, 'row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-variant-template>
                @include('inventory::items.variant-row', ['index' => '__INDEX__', 'row' => []])
            </template>
            <button type="button" data-add-variant class="text-sm font-semibold text-brand hover:text-brand-dark">+ {{ __('inventory::inventory.variant_add') }}</button>
            @if ($isNew)
                <p class="text-xs text-muted">{{ __('inventory::inventory.opening_stock_hint', ['store' => $defaultStore->name]) }}</p>
            @endif
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-item-form]');
                if (!form) return;
                var rows = form.querySelector('[data-variant-rows]');
                var template = form.querySelector('[data-variant-template]');
                var next = rows.children.length;

                function names() {
                    return [1, 2].map(function (n) { return form.querySelector('[data-option-name="' + n + '"]').value.trim(); });
                }

                function addRow(values) {
                    rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
                    var row = rows.lastElementChild;
                    Object.keys(values || {}).forEach(function (key) {
                        var input = row.querySelector('[data-field="' + key + '"]');
                        if (input) input.value = values[key];
                    });
                    return row;
                }

                function refresh() {
                    var n = names();
                    var hasOptions = n[0] !== '' || n[1] !== '';
                    [1, 2].forEach(function (i) {
                        var show = n[i - 1] !== '';
                        form.querySelectorAll('[data-option-col="' + i + '"]').forEach(function (el) {
                            el.classList.toggle('hidden', !show);
                            if (el.tagName === 'TH') el.textContent = n[i - 1];
                        });
                    });
                    form.querySelector('[data-generator]').classList.toggle('hidden', !hasOptions);
                    form.querySelector('[data-add-variant]').classList.toggle('hidden', !hasOptions);
                    form.querySelector('[data-variants-hint]').classList.toggle('hidden', !hasOptions);
                    form.querySelectorAll('[data-remove-variant]').forEach(function (el) { el.classList.toggle('hidden', !hasOptions); });
                    // Without options only the first row is used.
                    Array.prototype.forEach.call(rows.children, function (row, index) {
                        row.classList.toggle('hidden', !hasOptions && index > 0);
                    });
                }

                form.querySelectorAll('[data-option-name]').forEach(function (input) { input.addEventListener('input', refresh); });

                form.querySelector('[data-add-variant]').addEventListener('click', function () { addRow(); refresh(); });

                rows.addEventListener('click', function (event) {
                    var remove = event.target.closest('[data-remove-variant]');
                    if (remove && rows.children.length > 1) remove.closest('tr').remove();
                });

                form.querySelector('[data-generate]').addEventListener('click', function () {
                    var lists = [1, 2].map(function (n) {
                        var raw = form.querySelector('[data-values="' + n + '"]').value;
                        var values = raw.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
                        return values.length ? values : [''];
                    });
                    var existing = {};
                    Array.prototype.forEach.call(rows.children, function (row) {
                        var a = row.querySelector('[data-field="option1"]').value.trim().toLowerCase();
                        var b = row.querySelector('[data-field="option2"]').value.trim().toLowerCase();
                        if (a || b) existing[a + '|' + b] = true;
                        else row.remove();
                    });
                    lists[0].forEach(function (a) {
                        lists[1].forEach(function (b) {
                            if (!existing[a.toLowerCase() + '|' + b.toLowerCase()]) addRow({ option1: a, option2: b });
                        });
                    });
                    if (!rows.children.length) addRow();
                    refresh();
                });

                refresh();
            })();
        </script>
    @endpush
</x-layouts.app>
