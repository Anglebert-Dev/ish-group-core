<div class="bg-white border border-line rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-line flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-ink">{{ __('delivery::delivery.notes') }}</h2>
        @if ($order->status->reservesStock() && \App\Helpers\AuthHelper::can('create delivery.note'))
            <x-common.button href="{{ route('delivery.notes.create', $order) }}" size="sm">{{ __('delivery::delivery.add') }}</x-common.button>
        @endif
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-line">
            @forelse ($notes as $note)
                <tr>
                    <td class="px-5 py-3"><a href="{{ route('delivery.notes.show', $note) }}" class="font-medium text-ink hover:text-brand">{{ $note->number }}</a></td>
                    <td class="px-5 py-3 text-muted">{{ $note->delivered_on->format('d/m/Y') }}</td>
                    <td class="px-5 py-3">{{ $note->receiver_name }}</td>
                </tr>
            @empty
                <tr><td class="px-5 py-6 text-center text-muted">{{ __('delivery::delivery.none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
