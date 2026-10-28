{{--
    Product lines for documents (orders, invoices, quotes…).
    @include('inventory::partials.line-editor', ['lines' => $rows, 'options' => $itemService->lineOptions(), 'name' => 'lines'])
    Each row posts: variant (uuid), description, quantity, unit_price.
--}}
@php
    $name ??= 'lines';
    $rows = array_values($lines ?: [[]]);
    $cell = 'w-full rounded-lg border border-line bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand';
    $editorId = 'lines-'.\Illuminate\Support\Str::random(6);
@endphp
<div id="{{ $editorId }}" data-line-editor class="space-y-3">
    @error($name)<p class="text-sm text-danger">{{ $message }}</p>@enderror
    <div class="overflow-x-auto -mx-1">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide">
                    <th class="px-1 py-2 font-semibold min-w-[14rem]">{{ __('inventory::inventory.line_item') }}</th>
                    <th class="px-1 py-2 font-semibold min-w-[10rem]">{{ __('inventory::inventory.line_description') }}</th>
                    <th class="px-1 py-2 font-semibold w-24">{{ __('inventory::inventory.line_quantity') }}</th>
                    <th class="px-1 py-2 font-semibold w-32">{{ __('inventory::inventory.line_price') }}</th>
                    <th class="px-1 py-2 font-semibold w-32 text-right">{{ __('inventory::inventory.line_total') }}</th>
                    <th class="px-1 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody data-line-rows>
                @foreach ($rows as $i => $row)
                    @include('inventory::partials.line-row', ['index' => $i, 'row' => $row])
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="px-1 pt-3 text-right font-semibold text-ink">{{ __('inventory::inventory.subtotal') }}</td>
                    <td class="px-1 pt-3 text-right font-semibold text-ink" data-line-subtotal>0</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <template data-line-template>
        @include('inventory::partials.line-row', ['index' => '__INDEX__', 'row' => []])
    </template>
    <div class="flex flex-wrap items-center gap-4">
        <button type="button" data-line-add class="text-sm font-semibold text-brand hover:text-brand-dark">+ {{ __('inventory::inventory.line_add') }}</button>
        @php
            $gridItems = collect($options)->filter(fn ($o) => filled($o['option1_name'] ?? null) || filled($o['option2_name'] ?? null))
                ->groupBy('item')->filter(fn ($variants) => $variants->count() > 1);
        @endphp
        @if ($gridItems->isNotEmpty())
            <label class="flex items-center gap-2 text-sm">
                <span class="font-semibold text-brand">{{ __('inventory::inventory.grid_add') }}</span>
                <select data-grid-item class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-sm">
                    <option value="">{{ __('inventory::inventory.line_choose') }}</option>
                    @foreach ($gridItems as $uuid => $variants)
                        <option value="{{ $uuid }}">{{ $variants->first()['item_name'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>
    @if ($gridItems->isNotEmpty())
        <div data-grid class="hidden rounded-xl border border-dashed border-line p-4 space-y-3 overflow-x-auto">
            <p class="text-xs text-muted">{{ __('inventory::inventory.grid_hint') }}</p>
            <table class="text-sm" data-grid-table></table>
            <button type="button" data-grid-apply class="text-sm font-semibold text-brand">{{ __('inventory::inventory.grid_apply') }}</button>
        </div>
        <script type="application/json" data-grid-data>@json($gridItems->map(fn ($v) => $v->values()))</script>
    @endif
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-line-editor]').forEach(function (editor) {
                var rows = editor.querySelector('[data-line-rows]');
                var template = editor.querySelector('[data-line-template]');
                var next = rows.children.length;
                var format = function (n) { return n.toLocaleString(undefined, { maximumFractionDigits: 2 }); };

                function recalc() {
                    var subtotal = 0;
                    Array.prototype.forEach.call(rows.children, function (row) {
                        var qty = parseFloat(row.querySelector('[data-line="quantity"]').value) || 0;
                        var price = parseFloat(row.querySelector('[data-line="unit_price"]').value) || 0;
                        row.querySelector('[data-line-total]').textContent = format(qty * price);
                        subtotal += qty * price;
                    });
                    editor.querySelector('[data-line-subtotal]').textContent = format(subtotal);
                    editor.dispatchEvent(new CustomEvent('lines:changed', { bubbles: true, detail: { subtotal: subtotal } }));
                }

                editor.querySelector('[data-line-add]').addEventListener('click', function () {
                    rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
                    recalc();
                });

                rows.addEventListener('click', function (event) {
                    var remove = event.target.closest('[data-line-remove]');
                    if (remove && rows.children.length > 1) { remove.closest('tr').remove(); recalc(); }
                });

                rows.addEventListener('change', function (event) {
                    if (event.target.matches('[data-line="variant"]')) {
                        var option = event.target.selectedOptions[0];
                        var price = event.target.closest('tr').querySelector('[data-line="unit_price"]');
                        if (option && option.dataset.price && !price.value) price.value = option.dataset.price;
                    }
                    recalc();
                });
                rows.addEventListener('input', recalc);

                // Size × colour grid: one quantity per combination, added as lines.
                var gridSelect = editor.querySelector('[data-grid-item]');
                if (gridSelect) {
                    var grid = editor.querySelector('[data-grid]');
                    var table = editor.querySelector('[data-grid-table]');
                    var data = JSON.parse(editor.querySelector('[data-grid-data]').textContent);
                    var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
                    gridSelect.addEventListener('change', function () {
                        var variants = data[gridSelect.value] || [];
                        if (!variants.length) { grid.classList.add('hidden'); return; }
                        var rowsOpt = [], colsOpt = [];
                        variants.forEach(function (v) {
                            if (rowsOpt.indexOf(v.option1 || '') < 0) rowsOpt.push(v.option1 || '');
                            if (colsOpt.indexOf(v.option2 || '') < 0) colsOpt.push(v.option2 || '');
                        });
                        var html = '<tr><th class="px-2 py-1 text-left text-xs text-muted">' + esc(variants[0].option1_name) + ' \\ ' + esc(variants[0].option2_name) + '</th>';
                        colsOpt.forEach(function (c) { html += '<th class="px-2 py-1 text-xs text-muted">' + esc(c) + '</th>'; });
                        html += '</tr>';
                        rowsOpt.forEach(function (r) {
                            html += '<tr><th class="px-2 py-1 text-left font-semibold">' + esc(r) + '</th>';
                            colsOpt.forEach(function (c) {
                                var v = variants.find(function (x) { return (x.option1 || '') === r && (x.option2 || '') === c; });
                                html += '<td class="px-1 py-1">' + (v ? '<input type="number" min="0" step="any" data-grid-qty="' + v.uuid + '" data-price="' + (v.price || '') + '" class="w-20 rounded-lg border border-line px-2 py-1 text-right">' : '') + '</td>';
                            });
                            html += '</tr>';
                        });
                        table.innerHTML = html;
                        grid.classList.remove('hidden');
                    });
                    editor.querySelector('[data-grid-apply]').addEventListener('click', function () {
                        table.querySelectorAll('[data-grid-qty]').forEach(function (input) {
                            var qty = parseFloat(input.value);
                            if (!qty) return;
                            var empty = Array.prototype.find.call(rows.children, function (row) { return !row.querySelector('[data-line="variant"]').value; });
                            if (!empty) {
                                rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
                                empty = rows.lastElementChild;
                            }
                            empty.querySelector('[data-line="variant"]').value = input.dataset.gridQty;
                            empty.querySelector('[data-line="quantity"]').value = qty;
                            empty.querySelector('[data-line="unit_price"]').value = input.dataset.price || '';
                        });
                        table.innerHTML = '';
                        grid.classList.add('hidden');
                        gridSelect.value = '';
                        recalc();
                    });
                }
                recalc();
            });
        </script>
    @endpush
@endonce
