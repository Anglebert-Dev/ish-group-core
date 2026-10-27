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
    <button type="button" data-line-add class="text-sm font-semibold text-brand hover:text-brand-dark">+ {{ __('inventory::inventory.line_add') }}</button>
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
                recalc();
            });
        </script>
    @endpush
@endonce
