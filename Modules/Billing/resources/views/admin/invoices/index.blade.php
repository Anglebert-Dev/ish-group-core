<x-layouts.app :title="__('billing::billing.invoices')">
    @include('billing::admin.partials.nav')

    <x-common.page-header :title="__('billing::billing.invoices')" :description="__('billing::billing.invoices_description')" />

    <x-common.filter-panel :action="route('superadmin.billing.invoices.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('billing::billing.invoice_search')" compact />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'open' => __('billing::billing.invoice_open'), 'overdue' => __('billing::billing.overdue'), 'paid' => __('billing::billing.invoice_paid'), 'void' => __('billing::billing.invoice_void')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        @include('billing::admin.invoices.table', ['invoices' => $invoices])
        <x-common.pagination :paginator="$invoices" class="border-t border-line" />
    </div>
</x-layouts.app>
