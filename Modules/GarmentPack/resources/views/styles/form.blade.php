@php
    $isNew = ! $style->exists;
    $rows = old('materials', $style->exists ? $style->materials->map(fn ($m) => [
        'variant' => $m->variant?->uuid, 'quantity' => $m->quantity, 'waste_percent' => $m->waste_percent, 'note' => $m->note,
    ])->all() : []);
    $rows = $rows ?: [[]];
    $cell = 'w-full rounded-lg border border-line bg-white px-2.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand';
    $row = function ($index, $values) use ($materialOptions, $cell) {
        $html = '<tr class="align-top">';
        $html .= '<td class="px-1 py-1.5"><select name="materials['.$index.'][variant]" class="'.$cell.'"><option value="">'.e(__('garmentpack::garment.material_choose')).'</option>';
        foreach ($materialOptions as $option) {
            $html .= '<option value="'.e($option['uuid']).'"'.(($values['variant'] ?? '') === $option['uuid'] ? ' selected' : '').'>'.e($option['label']).' ('.e($option['unit']).')</option>';
        }
        $html .= '</select></td>';
        $html .= '<td class="px-1 py-1.5"><input type="number" step="any" min="0" name="materials['.$index.'][quantity]" value="'.e($values['quantity'] ?? '').'" class="'.$cell.'"></td>';
        $html .= '<td class="px-1 py-1.5"><input type="number" step="any" min="0" max="100" name="materials['.$index.'][waste_percent]" value="'.e($values['waste_percent'] ?? '').'" class="'.$cell.'"></td>';
        $html .= '<td class="px-1 py-1.5"><input type="text" name="materials['.$index.'][note]" value="'.e($values['note'] ?? '').'" class="'.$cell.'"></td>';
        $html .= '<td class="px-1 py-1.5 text-right"><button type="button" data-remove class="mt-2 text-xs font-semibold text-danger">✕</button></td></tr>';

        return $html;
    };
@endphp
<x-layouts.app :title="$isNew ? __('garmentpack::garment.add') : $style->code">
    <x-common.page-header :title="$isNew ? __('garmentpack::garment.add') : __('garmentpack::garment.edit')" :description="$isNew ? null : $style->code">
        <x-slot:actions>
            <x-common.button href="{{ $isNew ? route('garmentpack.styles.index') : route('garmentpack.styles.show', $style) }}" variant="secondary" size="sm">{{ __('body.cancel') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <form action="{{ $isNew ? route('garmentpack.styles.store') : route('garmentpack.styles.update', $style) }}" method="POST" class="space-y-6 max-w-5xl" data-style-form>
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <x-common.input name="code" :label="__('garmentpack::garment.code')" :value="old('code', $style->code)" required />
                <x-common.input name="name" :label="__('garmentpack::garment.name')" :value="old('name', $style->name)" required />
                <x-common.select name="item" :label="__('garmentpack::garment.product')" :value="old('item', $style->item?->uuid)" :options="$products" :hint="__('garmentpack::garment.product_hint')" searchable required />
                <x-common.input name="labour_cost" type="number" step="any" min="0" :label="__('garmentpack::garment.labour_cost')" :value="old('labour_cost', $style->labour_cost)" />
                <x-common.input name="overhead_cost" type="number" step="any" min="0" :label="__('garmentpack::garment.overhead_cost')" :value="old('overhead_cost', $style->overhead_cost)" />
            </div>
            <x-common.textarea name="description" :label="__('garmentpack::garment.description')" :value="old('description', $style->description)" :rows="2" />
            <div>
                <p class="text-sm font-semibold text-ink">{{ __('garmentpack::garment.piece_rates') }}</p>
                <p class="text-xs text-muted mb-2">{{ __('garmentpack::garment.piece_rates_hint') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($stages as $stage)
                        <x-common.input :name="'piece_rates['.$stage.']'" :id="'rate-'.\Illuminate\Support\Str::slug($stage)" type="number" step="any" min="0" :label="$stage" :value="old('piece_rates.'.$stage, $style->piece_rates[$stage] ?? '')" />
                    @endforeach
                </div>
            </div>
            @unless ($isNew)
                <input type="hidden" name="is_active" value="0">
                <x-common.checkbox name="is_active" value="1" :label="__('garmentpack::garment.active')" :checked="$style->is_active" />
            @endunless
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-4">
            <div>
                <h2 class="font-display font-bold text-ink">{{ __('garmentpack::garment.bom') }}</h2>
                <p class="text-sm text-muted">{{ __('garmentpack::garment.bom_hint') }}</p>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted uppercase tracking-wide">
                    <th class="px-1 py-2 font-semibold min-w-[14rem]">{{ __('garmentpack::garment.material') }}</th>
                    <th class="px-1 py-2 font-semibold w-28">{{ __('garmentpack::garment.per_piece') }}</th>
                    <th class="px-1 py-2 font-semibold w-24">{{ __('garmentpack::garment.waste') }}</th>
                    <th class="px-1 py-2 font-semibold">{{ __('garmentpack::garment.note') }}</th>
                    <th class="w-8"></th>
                </tr></thead>
                <tbody data-rows>
                    @foreach (array_values($rows) as $i => $values){!! $row($i, $values) !!}@endforeach
                </tbody>
            </table>
            <template data-template>{!! $row('__INDEX__', []) !!}</template>
            <button type="button" data-add class="text-sm font-semibold text-brand">+ {{ __('garmentpack::garment.material_add') }}</button>
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>

    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-style-form]');
                var rows = form.querySelector('[data-rows]');
                var next = rows.children.length;
                form.querySelector('[data-add]').addEventListener('click', function () {
                    rows.insertAdjacentHTML('beforeend', form.querySelector('[data-template]').innerHTML.replace(/__INDEX__/g, String(next++)));
                });
                rows.addEventListener('click', function (e) {
                    var remove = e.target.closest('[data-remove]');
                    if (remove && rows.children.length > 1) remove.closest('tr').remove();
                });
            })();
        </script>
    @endpush
</x-layouts.app>
