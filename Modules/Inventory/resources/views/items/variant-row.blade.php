@php
    $field = fn ($key) => 'variants['.$index.']['.$key.']';
    $cell = 'w-full min-w-[6rem] rounded-lg border border-line bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand';
@endphp
<tr class="align-top">
    <td class="px-1 py-1.5" data-option-col="1">
        <input type="hidden" name="{{ $field('id') }}" value="{{ $row['id'] ?? '' }}">
        <input type="text" name="{{ $field('option1') }}" value="{{ $row['option1'] ?? '' }}" data-field="option1" class="{{ $cell }}">
    </td>
    <td class="px-1 py-1.5" data-option-col="2"><input type="text" name="{{ $field('option2') }}" value="{{ $row['option2'] ?? '' }}" data-field="option2" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="text" name="{{ $field('sku') }}" value="{{ $row['sku'] ?? '' }}" data-field="sku" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('cost_price') }}" value="{{ $row['cost_price'] ?? '' }}" data-field="cost_price" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('sale_price') }}" value="{{ $row['sale_price'] ?? '' }}" data-field="sale_price" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('reorder_level') }}" value="{{ $row['reorder_level'] ?? '' }}" data-field="reorder_level" class="{{ $cell }}"></td>
    @if (! $item->exists)
        <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('opening') }}" value="{{ $row['opening'] ?? '' }}" data-field="opening" class="{{ $cell }}"></td>
    @endif
    <td class="px-1 py-1.5 text-right">
        <button type="button" data-remove-variant class="mt-2 text-xs font-semibold text-danger hover:opacity-80">{{ __('body.remove') }}</button>
    </td>
</tr>
