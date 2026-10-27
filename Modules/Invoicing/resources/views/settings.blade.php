<x-layouts.app :title="__('invoicing::invoicing.settings')">
    <x-common.page-header :title="__('invoicing::invoicing.settings')" :description="__('invoicing::invoicing.settings_description')" />

    <form action="{{ route('invoicing.settings.update') }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-2xl">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <x-common.input name="tax_rate" type="number" step="any" min="0" max="100" :label="__('invoicing::invoicing.tax_rate')" :value="old('tax_rate', $values['tax_rate'])" :hint="__('invoicing::invoicing.tax_rate_hint')" required />
            <x-common.input name="tax_id" :label="__('invoicing::invoicing.tax_id')" :value="old('tax_id', $values['tax_id'])" />
            <x-common.input name="phone" :label="__('invoicing::invoicing.phone')" :value="old('phone', $values['phone'])" />
        </div>
        <x-common.textarea name="address" :label="__('invoicing::invoicing.address')" :value="old('address', $values['address'])" :rows="2" />
        <x-common.textarea name="footer" :label="__('invoicing::invoicing.footer')" :value="old('footer', $values['footer'])" :rows="3" :hint="__('invoicing::invoicing.footer_hint')" />
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
