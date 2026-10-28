<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('garmentpack::garment.tickets_title', ['number' => $workOrder->number]) }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 16px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .ticket { border: 1.5px dashed #6B7280; border-radius: 8px; padding: 10px; page-break-inside: avoid; }
        .big { font-size: 26px; font-weight: 800; }
        .muted { color: #6B7280; font-size: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">🖨 {{ __('garmentpack::garment.bundles_tickets') }}</button></p>
    <div class="grid">
        @foreach ($bundles as $bundle)
            <div class="ticket">
                <div class="muted">{{ $workOrder->number }} · {{ __('garmentpack::garment.bundle') }}</div>
                <div class="big">#{{ $bundle->number }}</div>
                <div><b>{{ $workOrder->variant?->displayName() }}</b></div>
                <div>{{ __('garmentpack::garment.quantity') }}: <b>{{ \Modules\Inventory\Exceptions\InsufficientStock::format($bundle->quantity) }}</b></div>
                @if ($workOrder->line)<div class="muted">{{ $workOrder->line }}</div>@endif
            </div>
        @endforeach
    </div>
</body>
</html>
