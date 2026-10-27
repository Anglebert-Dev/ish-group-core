<x-layouts.app title="{{ __('body.maintenance') }}">
    <x-common.page-header :title="__('body.maintenance')" :description="__('body.maintenance_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('fleet.maintenance.export.pdf', request()->query())"
                :excel="route('fleet.maintenance.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('fleet.maintenance.create') }}" size="sm">+ {{ __('body.log_maintenance') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.vehicle') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.maintenance_type') }}</th>
                    <th class="px-5 py-3 font-semibold">Date</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.cost') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($maintenances as $record)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('fleet.maintenance.show', $record) }}" class="hover:text-brand">{{ $record->vehicle?->name() ?? '—' }}</a>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $record->maintenance_type->label() }}</td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $record->date->format('d M Y') }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $record->cost !== null ? number_format($record->cost, 2) . ' RWF' : '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="$record->status->label()" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('fleet.maintenance.show', $record) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('fleet.maintenance.edit', $record) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('fleet.maintenance.destroy', $record) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} this record?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-muted">No maintenance records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$maintenances" />
    </div>
</x-layouts.app>
