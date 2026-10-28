<x-layouts.app :title="$note->number">
    <x-common.page-header :title="$note->number" :description="$note->salesOrder?->customer?->displayName()">
        <x-slot:actions>
            <x-common.button href="{{ route('delivery.notes.index') }}" variant="secondary" size="sm">← {{ __('delivery::delivery.notes') }}</x-common.button>
            <x-common.button href="{{ route('delivery.notes.pdf', $note) }}" size="sm">{{ __('delivery::delivery.download_pdf') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
        <div class="xl:col-span-2 bg-white border border-line rounded-xl p-5 space-y-3">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-3 py-2.5 font-semibold">{{ __('inventory::inventory.line_item') }}</th>
                    <th class="px-3 py-2.5 font-semibold text-right">{{ __('inventory::inventory.line_quantity') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($note->lines as $line)
                        <tr>
                            <td class="px-3 py-3 font-medium text-ink">{{ $line->variant?->displayName() }}</td>
                            <td class="px-3 py-3 text-right">@include('inventory::partials.qty', ['value' => $line->quantity]) {{ $line->variant?->item?->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="space-y-4">
            <x-common.info-card :title="__('delivery::delivery.note')" :rows="[
                [__('delivery::delivery.order'), $note->salesOrder?->number],
                [__('delivery::delivery.delivered_on'), $note->delivered_on->format('d/m/Y')],
                [__('delivery::delivery.receiver_name'), $note->receiver_name],
                [__('delivery::delivery.receiver_phone'), $note->receiver_phone],
                [__('delivery::delivery.vehicle'), $note->vehicle],
            ]" />
            <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                <p class="text-sm font-semibold text-ink">{{ __('delivery::delivery.signature') }}</p>
                @if ($note->signature)
                    <img src="{{ $note->signature }}" alt="{{ __('delivery::delivery.signature') }}" class="max-h-32 rounded-lg border border-line bg-white">
                @else
                    <p class="text-sm text-muted">{{ __('delivery::delivery.signature_none') }}</p>
                @endif
            </div>
            @if ($note->salesOrder)
                <a href="{{ route('salesorders.orders.show', $note->salesOrder) }}" class="text-sm font-semibold text-brand">{{ $note->salesOrder->number }} →</a>
            @endif
        </div>
    </div>
</x-layouts.app>
