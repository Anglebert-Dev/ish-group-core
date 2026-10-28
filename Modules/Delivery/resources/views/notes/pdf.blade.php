<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .title { font-size: 20px; font-weight: bold; color: {{ $business?->brandColor() ?? '#124EB0' }}; }
        .muted { color: #6B7280; }
        table { width: 100%; border-collapse: collapse; }
        .lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #E5E7EB; padding: 6px 4px; }
        .lines td { border-bottom: 1px solid #F3F4F6; padding: 6px 4px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td><b style="font-size: 15px;">{{ $business?->companyName() }}</b><br><span class="muted">{{ $business?->contactEmail() }}</span></td>
            <td class="right"><div class="title">{{ __('delivery::delivery.pdf_title') }}</div><b>{{ $note->number }}</b><br>
                <span class="muted">{{ __('delivery::delivery.order') }}: {{ $note->salesOrder?->number }} · {{ $note->delivered_on->format('d/m/Y') }}</span></td>
        </tr>
    </table>
    <p style="margin-top: 18px;"><span class="muted">{{ __('delivery::delivery.customer') }}:</span> <b>{{ $note->salesOrder?->customer?->displayName() }}</b><br>
        <span class="muted">{{ $note->salesOrder?->customer?->delivery_address ?: $note->salesOrder?->customer?->address }}</span></p>

    <table class="lines" style="margin-top: 12px;">
        <thead><tr><th>{{ __('inventory::inventory.line_item') }}</th><th class="right">{{ __('inventory::inventory.line_quantity') }}</th></tr></thead>
        <tbody>
            @foreach ($note->lines as $line)
                <tr><td>{{ $line->variant?->displayName() }}</td><td class="right">{{ \Modules\Inventory\Exceptions\InsufficientStock::format($line->quantity) }} {{ $line->variant?->item?->unit }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 28px;">{{ __('delivery::delivery.pdf_received') }}: <b>{{ $note->receiver_name }}</b> {{ $note->receiver_phone }}</p>
    @if ($note->signature)<img src="{{ $note->signature }}" style="height: 70px;">@endif
    @if ($note->notes)<p class="muted">{{ $note->notes }}</p>@endif
</body>
</html>
