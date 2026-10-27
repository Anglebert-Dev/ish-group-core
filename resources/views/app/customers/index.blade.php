<x-layouts.app title="{{ __('body.customers') }}">
    <x-common.page-header :title="__('body.customers')" :description="__('body.customers_description', ['company' => $company])">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('customers.export.pdf', request()->query())"
                :excel="route('customers.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('customers.create') }}" size="sm">+ {{ __('body.add_customer') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">{{ $customer->name }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->email }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($customer->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('customers.show', $customer) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$customers" />
    </div>
</x-layouts.app>
