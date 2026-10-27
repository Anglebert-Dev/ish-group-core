<x-layouts.app :title="__('body.customers')">
    <x-common.page-header :title="__('body.customers')" :description="__('body.customers_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('customers.export.pdf', request()->query())"
                :excel="route('customers.export.excel', request()->query())"
            />
            @if (\App\Helpers\AuthHelper::can('create customers.customer'))
                <x-common.button href="{{ route('customers.create') }}" size="sm">+ {{ __('body.customer_add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('customers.index')" :open="array_filter($filters) !== []">
        <x-common.input name="q" :label="__('body.search')" :value="$filters['q'] ?? ''" :placeholder="__('body.customer_search_placeholder')" compact />
        <x-common.select name="type" :label="__('body.type')" :value="$filters['type'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'company' => __('body.customer_type_company'), 'person' => __('body.customer_type_person')]" />
        <x-common.select name="status" :label="__('body.status')" :value="$filters['status'] ?? ''" compact hide-placeholder
            :options="['' => __('body.all'), 'active' => __('body.customer_status_active'), 'inactive' => __('body.customer_status_inactive'), 'banned' => __('body.customer_status_banned')]" />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.customer_contact_person') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('customers.show', $customer) }}" class="font-medium text-ink hover:text-brand">{{ $customer->displayName() }}</a>
                            <p class="text-xs text-muted">{{ $customer->type?->label() }}{{ $customer->hasPortalPassword() ? ' · '.__('body.customer_has_portal') : '' }}</p>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->contact_person ?: ($customer->type?->value === 'person' ? $customer->name : '—') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->phone ?: '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->email ?: '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$customer->status->color()">{{ $customer->status->label() }}</x-common.badge></td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <x-common.action-button :href="route('customers.show', $customer)">{{ __('body.manage') }}</x-common.action-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.customer_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$customers" />
    </div>
</x-layouts.app>
