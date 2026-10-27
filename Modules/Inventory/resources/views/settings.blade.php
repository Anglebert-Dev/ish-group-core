<x-layouts.app :title="__('inventory::inventory.settings')">
    <x-common.page-header :title="__('inventory::inventory.settings')" :description="__('inventory::inventory.settings_description')" />

    <form action="{{ route('inventory.settings.update') }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-xl">
        @csrf @method('PUT')
        <x-common.textarea name="units" :label="__('inventory::inventory.units')" :value="old('units', implode(PHP_EOL, $units))" :rows="10" :hint="__('inventory::inventory.units_hint')" required />
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
