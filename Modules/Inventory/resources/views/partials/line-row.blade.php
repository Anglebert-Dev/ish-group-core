@php
    $field = fn ($key) => $name.'['.$index.']['.$key.']';
    $cell = 'w-full rounded-lg border border-line bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand';
    $selected = $row['variant'] ?? '';
@endphp
<tr class="align-top">
    <td class="px-1 py-1.5">
        <select name="{{ $field('variant') }}" data-line="variant" class="{{ $cell }}">
            <option value="">{{ __('inventory::inventory.line_choose') }}</option>
            @foreach ($options as $option)
                <option value="{{ $option['uuid'] }}" data-price="{{ $option['price'] }}" @selected($selected === $option['uuid'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-1 py-1.5"><input type="text" name="{{ $field('description') }}" value="{{ $row['description'] ?? '' }}" data-line="description" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('quantity') }}" value="{{ $row['quantity'] ?? '' }}" data-line="quantity" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5"><input type="number" step="any" min="0" name="{{ $field('unit_price') }}" value="{{ $row['unit_price'] ?? '' }}" data-line="unit_price" class="{{ $cell }}"></td>
    <td class="px-1 py-1.5 pt-3.5 text-right whitespace-nowrap" data-line-total>0</td>
    <td class="px-1 py-1.5 text-right"><button type="button" data-line-remove class="mt-2 text-xs font-semibold text-danger hover:opacity-80" aria-label="{{ __('body.remove') }}">✕</button></td>
</tr>
