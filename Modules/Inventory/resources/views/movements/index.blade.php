@php
    $types = collect(\Modules\Inventory\Enums\MovementType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp
<x-layouts.app :title="__('inventory::inventory.movements')">
    <x-common.page-header :title="__('inventory::inventory.movements')" :description="$item ? $item->name : __('inventory::inventory.movements_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('inventory.movements.export.pdf', request()->query())"
                :excel="route('inventory.movements.export.excel', request()->query())"
            />
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('inventory.movements.index')" :open="array_filter($filters) !== []">
        @if ($item)<input type="hidden" name="item" value="{{ $item->uuid }}">@endif
        <x-common.select name="store" :label="__('inventory::inventory.store')" :value="$filters['store'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all')] + $stores->mapWithKeys(fn ($s) => [$s->uuid => $s->name])->all()" />
        <x-common.select name="type" :label="__('body.type')" :value="$filters['type'] ?? ''" compact hide-placeholder :options="['' => __('body.all')] + $types" />
        <x-common.input name="from" type="date" :label="__('body.audit_from')" :value="$filters['from'] ?? ''" compact />
        <x-common.input name="to" type="date" :label="__('body.audit_to')" :value="$filters['to'] ?? ''" compact />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        @include('inventory::movements.table', ['movements' => $movements, 'showItem' => true])
        <x-common.pagination :paginator="$movements" />
    </div>
</x-layouts.app>
