<x-layouts.app :title="__('invoicing::invoicing.settings')">
    <x-common.page-header :title="__('invoicing::invoicing.settings')" :description="__('invoicing::invoicing.settings_description')" />

    <form action="{{ route('invoicing.settings.update') }}" method="POST" class="bg-white border border-line rounded-xl p-5 sm:p-6 space-y-5 max-w-2xl">
        @csrf @method('PUT')
        <x-common.input name="tax_rate" type="number" step="any" min="0" max="100" :label="__('invoicing::invoicing.tax_rate')" :value="old('tax_rate', $values['tax_rate'])" :hint="__('invoicing::invoicing.tax_rate_hint')" required />

        <div class="rounded-xl border border-line bg-surface px-4 py-3 text-sm text-muted">
            {{ __('invoicing::invoicing.settings_moved') }}
            @can('list settings.setting')
                <a href="{{ route('settings.index', ['tab' => 'details']) }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('invoicing::invoicing.settings_moved_details') }}</a>
                ·
                <a href="{{ route('settings.index', ['tab' => 'documents']) }}" class="font-semibold text-brand hover:text-brand-dark">{{ __('invoicing::invoicing.settings_moved_documents') }}</a>
            @endcan
        </div>

        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
