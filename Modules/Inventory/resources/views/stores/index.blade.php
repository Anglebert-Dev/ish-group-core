@php $canUpdate = \App\Helpers\AuthHelper::can('update inventory.store'); @endphp
<x-layouts.app :title="__('inventory::inventory.stores')">
    <x-common.page-header :title="__('inventory::inventory.stores')" :description="__('inventory::inventory.stores_description')" />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 space-y-4">
            @forelse ($stores as $store)
                <div class="bg-white border border-line rounded-xl p-5 space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-display font-bold text-ink">{{ $store->name }} @if ($store->code)<span class="text-sm font-normal text-muted">· {{ $store->code }}</span>@endif</p>
                            <p class="text-sm text-muted">{{ __('inventory::inventory.store_items') }}: {{ $itemCounts[$store->id] ?? 0 }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($store->is_default)
                                <x-common.badge>{{ __('inventory::inventory.store_default') }}</x-common.badge>
                            @endif
                            @unless ($store->is_active)
                                <x-common.badge color="muted">{{ __('inventory::inventory.status_inactive') }}</x-common.badge>
                            @endunless
                            @if ($canUpdate && ! $store->is_default)
                                <x-common.action-button :action="route('inventory.stores.default', $store)" method="PUT">{{ __('inventory::inventory.store_make_default') }}</x-common.action-button>
                            @endif
                            @if (! $store->is_default && \App\Helpers\AuthHelper::can('delete inventory.store'))
                                <form action="{{ route('inventory.stores.destroy', $store) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center rounded-lg border border-red-300 bg-white px-2.5 py-1 text-xs font-semibold text-danger hover:bg-red-50"
                                        data-confirm data-confirm-title="{{ __('body.delete') }} {{ $store->name }}?" data-confirm-variant="danger">{{ __('body.delete') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @if ($canUpdate)
                        <details>
                            <summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('inventory::inventory.store_edit') }}</summary>
                            <form action="{{ route('inventory.stores.update', $store) }}" method="POST" class="mt-4 space-y-4">
                                @csrf @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <x-common.input name="name" :id="'store-name-'.$store->id" :label="__('body.name')" :value="$store->name" required />
                                    <x-common.input name="code" :id="'store-code-'.$store->id" :label="__('inventory::inventory.store_code')" :value="$store->code" />
                                </div>
                                <x-common.textarea name="address" :id="'store-address-'.$store->id" :label="__('body.address')" :value="$store->address" :rows="2" />
                                <input type="hidden" name="is_active" value="0">
                                <x-common.checkbox name="is_active" :id="'store-active-'.$store->id" value="1" :label="__('inventory::inventory.status_active')" :checked="$store->is_active" />
                                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <div class="bg-white border border-line rounded-xl p-10 text-center text-muted">{{ __('inventory::inventory.store_none') }}</div>
            @endforelse
        </div>

        @if (\App\Helpers\AuthHelper::can('create inventory.store'))
            <form action="{{ route('inventory.stores.store') }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                @csrf
                <h2 class="font-display font-bold text-ink">{{ __('inventory::inventory.store_add') }}</h2>
                <x-common.input name="name" :label="__('body.name')" :value="old('name')" required />
                <x-common.input name="code" :label="__('inventory::inventory.store_code')" :value="old('code')" />
                <x-common.textarea name="address" :label="__('body.address')" :value="old('address')" :rows="2" />
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </form>
        @endif
    </div>
</x-layouts.app>
