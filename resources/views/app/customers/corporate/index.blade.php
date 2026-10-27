<x-layouts.app title="{{ __('body.corporate_customers') }}">
    <x-common.page-header :title="__('body.corporate_customers')" :description="__('body.corporate_customers_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('customers.corporate.export.pdf', request()->query())"
                :excel="route('customers.corporate.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('customers.corporate.create') }}" size="sm">+ {{ __('body.add_corporate_customer') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.company_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($corporateCustomers as $corporate)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('customers.corporate.show', $corporate) }}" class="hover:text-brand">{{ $corporate->company_name }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $corporate->email ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $corporate->phone ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($corporate->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('customers.corporate.show', $corporate) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('customers.corporate.edit', $corporate) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('customers.corporate.destroy', $corporate) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $corporate->company_name }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">No corporate customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$corporateCustomers" />
    </div>
</x-layouts.app>
