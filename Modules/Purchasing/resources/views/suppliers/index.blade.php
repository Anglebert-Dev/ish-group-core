<x-layouts.app :title="__('purchasing::purchasing.suppliers')">
    <x-common.page-header :title="__('purchasing::purchasing.suppliers')" :description="__('purchasing::purchasing.suppliers_description')">
        <x-slot:actions>
            @if (\App\Helpers\AuthHelper::can('create purchasing.supplier'))
                <x-common.button href="{{ route('purchasing.suppliers.create') }}" size="sm">+ {{ __('purchasing::purchasing.supplier_add') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    <x-common.filter-panel :action="route('purchasing.suppliers.index')" :open="filled($q)">
        <x-common.input name="q" :label="__('body.search')" :value="$q" compact />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('purchasing::purchasing.contact_person') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.phone') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($suppliers as $supplier)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5"><a href="{{ route('purchasing.suppliers.show', $supplier) }}" class="font-medium text-ink hover:text-brand">{{ $supplier->name }}</a></td>
                        <td class="px-5 py-3.5">{{ $supplier->contact_person ?: '—' }}</td>
                        <td class="px-5 py-3.5">{{ $supplier->phone ?: '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.badge :color="$supplier->is_active ? 'success' : 'muted'">{{ $supplier->is_active ? __('purchasing::purchasing.active') : __('inventory::inventory.status_inactive') }}</x-common.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('purchasing::purchasing.suppliers_none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-common.pagination :paginator="$suppliers" />
    </div>
</x-layouts.app>
