@php use Modules\Billing\Support\Money; @endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1F2937; margin: 0; }
        .page { padding: 32px 36px; }
        h1 { font-size: 22px; margin: 0; color: #111827; }
        .muted { color: #6B7280; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6B7280; border-bottom: 1px solid #E5E7EB; padding: 8px 6px; }
        table.lines td { padding: 8px 6px; border-bottom: 1px solid #F3F4F6; }
        .right { text-align: right; }
        .totals { width: 260px; margin-left: auto; margin-top: 16px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .grand td { font-weight: bold; font-size: 13px; border-top: 1px solid #E5E7EB; padding-top: 8px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .paid { background: #DCFCE7; color: #15803D; } .open { background: #FEF3C7; color: #B45309; } .void { background: #F3F4F6; color: #6B7280; }
        .box { margin-top: 24px; padding: 12px; background: #F8FAFC; border: 1px solid #E5E7EB; border-radius: 6px; }
    </style>
</head>
<body>
<div class="page">
    <table class="row">
        <tr>
            <td>
                <h1>{{ $issuer['name'] }}</h1>
                @if ($issuer['address'])<div class="muted">{!! nl2br(e($issuer['address'])) !!}</div>@endif
                @if ($issuer['tax_id'])<div class="muted">TIN {{ $issuer['tax_id'] }}</div>@endif
                @if ($issuer['email'])<div class="muted">{{ $issuer['email'] }}</div>@endif
                @if ($issuer['phone'])<div class="muted">{{ $issuer['phone'] }}</div>@endif
            </td>
            <td class="right">
                <div style="font-size: 18px; font-weight: bold;">{{ __('billing::billing.invoice') }}</div>
                <div style="font-size: 13px;">{{ $invoice->number }}</div>
                <div style="margin-top: 6px;"><span class="badge {{ $invoice->status->value }}">{{ $invoice->status->label() }}</span></div>
            </td>
        </tr>
    </table>

    <table class="row" style="margin-top: 24px;">
        <tr>
            <td>
                <div class="muted">{{ __('billing::billing.billed_to') }}</div>
                <div style="font-weight: bold;">{{ $invoice->billed_to['name'] ?? $invoice->business?->name }}</div>
                @if (! empty($invoice->billed_to['address']))<div>{!! nl2br(e($invoice->billed_to['address'])) !!}</div>@endif
                @if (! empty($invoice->billed_to['tax_id']))<div>TIN {{ $invoice->billed_to['tax_id'] }}</div>@endif
                @if (! empty($invoice->billed_to['email']))<div>{{ $invoice->billed_to['email'] }}</div>@endif
            </td>
            <td class="right">
                <div><span class="muted">{{ __('billing::billing.issued') }}:</span> {{ $invoice->issued_at?->format('d M Y') }}</div>
                <div><span class="muted">{{ __('billing::billing.due') }}:</span> {{ $invoice->due_at?->format('d M Y') }}</div>
                @if ($invoice->paid_at)<div><span class="muted">{{ __('billing::billing.paid_on') }}:</span> {{ $invoice->paid_at->format('d M Y') }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th>{{ __('body.description') }}</th><th class="right">{{ __('body.amount') }}</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr><td>{{ $line->description }}</td><td class="right">{{ Money::format($line->amount, $invoice->currency) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">{{ __('billing::billing.subtotal') }}</td><td class="right">{{ Money::format($invoice->subtotal, $invoice->currency) }}</td></tr>
        @if ((float) $invoice->discount > 0)<tr><td class="muted">{{ __('billing::billing.discount') }}</td><td class="right">− {{ Money::format($invoice->discount, $invoice->currency) }}</td></tr>@endif
        @if ((float) $invoice->credit > 0)<tr><td class="muted">{{ __('billing::billing.credit') }}</td><td class="right">− {{ Money::format($invoice->credit, $invoice->currency) }}</td></tr>@endif
        @if ((float) $invoice->tax > 0)<tr><td class="muted">{{ $invoice->tax_label ?: __('billing::billing.tax') }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%{{ $invoice->tax_inclusive ? ' ('.__('billing::billing.included').')' : '' }}</td><td class="right">{{ Money::format($invoice->tax, $invoice->currency) }}</td></tr>@endif
        <tr class="grand"><td>{{ __('billing::billing.total') }}</td><td class="right">{{ Money::format($invoice->total, $invoice->currency) }}</td></tr>
        @if ((float) $invoice->amount_paid > 0)
            <tr><td class="muted">{{ __('billing::billing.amount_paid') }}</td><td class="right">{{ Money::format($invoice->amount_paid, $invoice->currency) }}</td></tr>
            <tr><td><strong>{{ __('billing::billing.balance_due') }}</strong></td><td class="right"><strong>{{ Money::format(Money::fromCents($invoice->balanceCents()), $invoice->currency) }}</strong></td></tr>
        @endif
    </table>

    @if ($invoice->isOpen() && filled($instructions))
        <div class="box">
            <strong>{{ __('billing::billing.how_to_pay') }}</strong>
            <div style="margin-top: 4px;">{!! nl2br(e($instructions)) !!}</div>
        </div>
    @endif
</div>
</body>
</html>
