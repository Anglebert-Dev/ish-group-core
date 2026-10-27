<div class="overflow-x-auto">
<table class="w-full text-sm">
    <thead>
        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
            <th class="px-5 py-3 font-semibold">{{ __('body.when') }}</th>
            @if ($showItem)<th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.item') }}</th>@endif
            @unless ($showItem)<th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.variant') }}</th>@endunless
            <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.store') }}</th>
            <th class="px-5 py-3 font-semibold">{{ __('body.type') }}</th>
            <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.quantity') }}</th>
            <th class="px-5 py-3 font-semibold text-right">{{ __('inventory::inventory.balance_after') }}</th>
            <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.reason') }}</th>
            <th class="px-5 py-3 font-semibold">{{ __('inventory::inventory.by') }}</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-line">
        @forelse ($movements as $movement)
            <tr>
                <td class="px-5 py-3 whitespace-nowrap text-muted">@datetime($movement->created_at)</td>
                @if ($showItem)
                    <td class="px-5 py-3">
                        @if ($movement->item)
                            <a href="{{ route('inventory.items.show', $movement->item) }}" class="font-medium text-ink hover:text-brand">{{ $movement->item->name }}</a>
                        @endif
                        @if ($movement->variant?->optionLabel())<p class="text-xs text-muted">{{ $movement->variant->optionLabel() }}</p>@endif
                    </td>
                @else
                    <td class="px-5 py-3">{{ $movement->variant?->optionLabel() ?: '—' }}</td>
                @endif
                <td class="px-5 py-3 whitespace-nowrap">{{ $movement->store?->name }}</td>
                <td class="px-5 py-3 whitespace-nowrap"><x-common.badge :color="$movement->type->color()">{{ $movement->type->label() }}</x-common.badge></td>
                <td class="px-5 py-3 text-right whitespace-nowrap font-semibold {{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}@include('inventory::partials.qty', ['value' => $movement->quantity])</td>
                <td class="px-5 py-3 text-right whitespace-nowrap">@include('inventory::partials.qty', ['value' => $movement->balance_after])</td>
                <td class="px-5 py-3 text-muted">
                    {{ $movement->reason ?: '—' }}
                    @if ($movement->reference_label)<span class="block text-xs">{{ $movement->reference_label }}</span>@endif
                </td>
                <td class="px-5 py-3 whitespace-nowrap text-muted">{{ $movement->user?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-muted">{{ __('inventory::inventory.movement_none') }}</td></tr>
        @endforelse
    </tbody>
</table>
</div>
