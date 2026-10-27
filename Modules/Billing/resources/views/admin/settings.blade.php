<x-layouts.app :title="__('billing::billing.settings')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.settings')" :description="__('billing::billing.settings_description')" />

    <form action="{{ route('superadmin.billing.settings.update') }}" method="POST" class="space-y-6 max-w-5xl">
        @csrf @method('PUT')

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.money') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <x-common.input name="currency" :label="__('billing::billing.currency')" :value="old('currency', $settings['currency'])" :hint="__('billing::billing.currency_hint')" maxlength="3" required />
                <x-common.input name="tax_label" :label="__('billing::billing.tax_label')" :value="old('tax_label', $settings['tax_label'])" />
                <x-common.input name="tax_rate" type="number" step="0.001" min="0" max="100" :label="__('billing::billing.tax_rate')" :value="old('tax_rate', $settings['tax_rate'])" :hint="__('billing::billing.tax_rate_hint')" required />
                <x-common.input name="invoice_prefix" :label="__('billing::billing.invoice_prefix')" :value="old('invoice_prefix', $settings['invoice_prefix'])" required />
            </div>
            <input type="hidden" name="tax_inclusive" value="0">
            <x-common.checkbox name="tax_inclusive" :label="__('billing::billing.tax_inclusive')" :description="__('billing::billing.tax_inclusive_hint')" :checked="$settings['tax_inclusive'] === '1'" />
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.rules') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <x-common.input name="due_days" type="number" min="0" :label="__('billing::billing.due_days')" :value="old('due_days', $settings['due_days'])" :hint="__('billing::billing.due_days_hint')" required />
                <x-common.input name="reminder_days" type="number" min="0" :label="__('billing::billing.reminder_days')" :value="old('reminder_days', $settings['reminder_days'])" :hint="__('billing::billing.reminder_days_hint')" required />
                <x-common.input name="grace_days" type="number" min="0" :label="__('billing::billing.grace_days')" :value="old('grace_days', $settings['grace_days'])" :hint="__('billing::billing.grace_days_hint')" required />
                <x-common.select name="default_package_id" :label="__('billing::billing.default_package')" :value="old('default_package_id', $settings['default_package_id'])" :options="['' => __('body.none')] + $packages->all()" :hint="__('billing::billing.default_package_hint')" hide-placeholder />
            </div>
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.how_to_pay') }}</h2>
            <x-common.textarea name="payment_instructions" :label="__('billing::billing.payment_instructions')" :value="old('payment_instructions', $settings['payment_instructions'])" :rows="5" :hint="__('billing::billing.payment_instructions_hint')" />
        </div>

        <div class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5">
            <h2 class="font-display font-bold text-ink">{{ __('billing::billing.issuer') }}</h2>
            <p class="text-sm text-muted -mt-3">{{ __('billing::billing.issuer_hint') }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <x-common.input name="issuer_name" :label="__('body.name')" :value="old('issuer_name', $settings['issuer_name'])" />
                <x-common.input name="issuer_tax_id" :label="__('billing::billing.tax_id')" :value="old('issuer_tax_id', $settings['issuer_tax_id'])" />
                <x-common.input name="issuer_email" type="email" :label="__('body.email_address')" :value="old('issuer_email', $settings['issuer_email'])" />
                <x-common.input name="issuer_phone" :label="__('body.phone')" :value="old('issuer_phone', $settings['issuer_phone'])" />
            </div>
            <x-common.textarea name="issuer_address" :label="__('body.address')" :value="old('issuer_address', $settings['issuer_address'])" :rows="2" />
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
