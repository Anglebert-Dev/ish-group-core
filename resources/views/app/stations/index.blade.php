<x-layouts.app title="{{ __('body.stations') }}">
    <x-common.page-header :title="__('body.stations')" :description="__('body.stations_description')">
        <x-slot:actions>
            <x-common.export-buttons
                :pdf="route('stations.export.pdf', request()->query())"
                :excel="route('stations.export.excel', request()->query())"
            />
            <x-common.button href="{{ route('stations.create') }}" size="sm">+ {{ __('body.add_station') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.station_name') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.code') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.city') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($stations as $station)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                            <a href="{{ route('stations.show', $station) }}" class="hover:text-brand">{{ $station->name }}</a>
                        </td>
                        <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ $station->code }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $station->city ?? '—' }}</td>
                        <td class="px-5 py-3.5"><x-common.status :status="ucfirst($station->status->value)" /></td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('stations.show', $station) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.view') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <a href="{{ route('stations.edit', $station) }}" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.edit') }}</a>
                            <span class="text-line mx-1.5">|</span>
                            <form action="{{ route('stations.destroy', $station) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                    data-confirm
                                    data-confirm-title="{{ __('body.delete') }} {{ $station->name }}?"
                                    data-confirm-variant="danger"
                                    data-confirm-text="{{ __('body.delete') }}"
                                >{{ __('body.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">No stations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$stations" />
    </div>
</x-layouts.app>
