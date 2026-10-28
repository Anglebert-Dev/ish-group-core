<x-layouts.app :title="__('delivery::delivery.notes')">
    <x-common.page-header :title="__('delivery::delivery.notes')" :description="__('delivery::delivery.notes_description')" />

    <x-common.filter-panel :action="route('delivery.notes.index')" :open="filled($q)">
        <x-common.input name="q" :label="__('body.search')" :value="$q" :placeholder="__('delivery::delivery.search_placeholder')" compact />
    </x-common.filter-panel>

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('delivery::delivery.number') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('delivery::delivery.order') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('delivery::delivery.customer') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('delivery::delivery.delivered_on') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('delivery::delivery.receiver_name') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($notes as $note)
                    <tr class="hover:bg-surface">
                        <td class="px-5 py-3.5"><a href="{{ route('delivery.notes.show', $note) }}" class="font-medium text-ink hover:text-brand">{{ $note->number }}</a></td>
                        <td class="px-5 py-3.5">{{ $note->salesOrder?->number }}</td>
                        <td class="px-5 py-3.5">{{ $note->salesOrder?->customer?->displayName() }}</td>
                        <td class="px-5 py-3.5 whitespace-nowrap">{{ $note->delivered_on->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5">{{ $note->receiver_name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-muted">{{ __('delivery::delivery.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <x-common.pagination :paginator="$notes" />
    </div>
</x-layouts.app>
