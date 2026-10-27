<x-layouts.app title="{{ __('body.suppliers') }}">
    <x-common.page-header :title="__('body.suppliers')" :description="__('body.suppliers_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('finance.suppliers.export.pdf', request()->query())"
                :excel="route('finance.suppliers.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('finance.suppliers.create') }}" size="sm">+ {{ __('body.add_supplier') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.supplier_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.supplier_type') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.contact_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('finance.suppliers.show', $supplier) }}" class="hover:text-brand">{{ $supplier->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $supplier->type->label() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $supplier->contact_name ?? '—' }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $supplier->phone ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$supplier->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('finance.suppliers.show', $supplier) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('finance.suppliers.edit', $supplier) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('finance.suppliers.destroy', $supplier) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $supplier->name }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_suppliers_yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$suppliers" />
    </div>
</x-layouts.app>
