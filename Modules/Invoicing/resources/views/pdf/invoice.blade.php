<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .head { width: 100%; margin-bottom: 24px; }
        .head td { vertical-align: top; }
        .title { font-size: 22px; font-weight: bold; color: {{ $issuer['color'] }}; }
        .muted { color: #6B7280; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #E5E7EB; padding: 6px 4px; }
        table.lines td { border-bottom: 1px solid #F3F4F6; padding: 6px 4px; }
        .right { text-align: right; }
        .totals td { padding: 3px 4px; }
        .strong { font-weight: bold; }
        .stamp { font-size: 16px; font-weight: bold; color: #B91C1C; border: 2px solid #B91C1C; padding: 4px 10px; display: inline-block; }
        .footer { margin-top: 28px; font-size: 10px; color: #4B5563; white-space: pre-line; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="strong" style="font-size: 15px;">{{ $issuer['name'] }}</div>
                @if ($issuer['address'])<div class="muted">{{ $issuer['address'] }}</div>@endif
                @if ($issuer['phone'])<div class="muted">{{ $issuer['phone'] }}</div>@endif
                @if ($issuer['email'])<div class="muted">{{ $issuer['email'] }}</div>@endif
                @if ($issuer['tax_id'])<div class="muted">TIN: {{ $issuer['tax_id'] }}</div>@endif
            </td>
            <td class="right">
                <div class="title">{{ __('invoicing::invoicing.pdf_invoice') }}</div>
                <div class="strong">{{ $invoice->number }}</div>
                <div class="muted">{{ __('invoicing::invoicing.invoice_date') }}: {{ $invoice->invoice_date->format('d/m/Y') }}</div>
                @if ($invoice->due_date)<div class="muted">{{ __('invoicing::invoicing.due_date') }}: {{ $invoice->due_date->format('d/m/Y') }}</div>@endif
                @if ($invoice->status === \Modules\Invoicing\Enums\InvoiceStatus::Cancelled)
                    <div style="margin-top: 6px;"><span class="stamp">{{ __('invoicing::invoicing.credit_note', ['number' => $invoice->credit_note_number]) }}</span></div>
                @endif
            </td>
        </tr>
    </table>

    <div class="muted" style="text-transform: uppercase; font-size: 10px;">{{ __('invoicing::invoicing.pdf_bill_to') }}</div>
    <div class="strong">{{ $invoice->customer?->displayName() }}</div>
    @if ($invoice->customer?->contact_person)<div>{{ $invoice->customer->contact_person }}</div>@endif
    @if ($invoice->customer?->address)<div class="muted">{{ $invoice->customer->address }}</div>@endif
    @if ($invoice->customer?->phone)<div class="muted">{{ $invoice->customer->phone }}</div>@endif
    @if ($invoice->customer?->tax_id)<div class="muted">TIN: {{ $invoice->customer->tax_id }}</div>@endif

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('inventory::inventory.line_item') }}</th>
                <th class="right">{{ __('inventory::inventory.line_quantity') }}</th>
                <th class="right">{{ __('inventory::inventory.line_price') }}</th>
                <th class="right">{{ __('inventory::inventory.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->variant?->displayName() }}@if ($line->description)<br><span class="muted">{{ $line->description }}</span>@endif</td>
                    <td class="right">{{ \Modules\Inventory\Exceptions\InsufficientStock::format($line->quantity) }} {{ $line->variant?->item?->unit }}</td>
                    <td class="right">{{ money($line->unit_price) }}</td>
                    <td class="right">{{ money($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 45%; margin-left: 55%; margin-top: 10px;">
        <tr><td>{{ __('inventory::inventory.subtotal') }}</td><td class="right">{{ money($invoice->subtotal) }}</td></tr>
        @if ((float) $invoice->discount > 0)<tr><td>{{ __('inventory::inventory.discount') }}</td><td class="right">− {{ money($invoice->discount) }}</td></tr>@endif
        @if ((float) $invoice->tax > 0)<tr><td>{{ __('invoicing::invoicing.tax_named', ['rate' => rtrim(rtrim(number_format($invoice->tax_rate, 3), '0'), '.')]) }}</td><td class="right">{{ money($invoice->tax) }}</td></tr>@endif
        <tr><td class="strong">{{ __('inventory::inventory.total') }}</td><td class="right strong">{{ money($invoice->total) }}</td></tr>
        <tr><td>{{ __('invoicing::invoicing.paid') }}</td><td class="right">{{ money($invoice->paid) }}</td></tr>
        <tr><td class="strong">{{ __('invoicing::invoicing.pdf_page_total') }}</td><td class="right strong">{{ money($invoice->balance()) }}</td></tr>
    </table>

    @if ($footer)<div class="footer">{{ $footer }}</div>@endif
</body>
</html>
