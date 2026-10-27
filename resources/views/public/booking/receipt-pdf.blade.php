<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('body.booking_invoice') }} — {{ $booking->booking_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 32px; }
        .header { margin-bottom: 24px; border-bottom: 2px solid {{ $brandColor }}; padding-bottom: 16px; }
        .header img { height: 40px; margin-bottom: 8px; }
        .company { font-size: 16px; font-weight: bold; color: {{ $brandColor }}; }
        .title { font-size: 20px; font-weight: bold; margin-top: 8px; }
        .meta { font-size: 10px; color: #667085; margin-top: 4px; }
        .ref { font-size: 14px; font-weight: bold; color: {{ $brandColor }}; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; color: #667085; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        td.label { width: 40%; color: #667085; }
        td.value { font-weight: 600; }
        .section { margin-top: 22px; }
        .section-title { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        .totals { margin-top: 20px; border-top: 2px solid {{ $brandColor }}; padding-top: 12px; }
        .totals .row { display: table; width: 100%; margin-bottom: 6px; }
        .totals .row span { display: table-cell; }
        .totals .row .right { text-align: right; font-weight: bold; }
        .grand { font-size: 16px; color: {{ $brandColor }}; }
        .footer { margin-top: 28px; font-size: 10px; color: #667085; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt="">
        @endif
        <div class="company">{{ $company }}</div>
        <div class="title">{{ __('body.booking_invoice') }}</div>
        <div class="ref">{{ $booking->booking_number }}</div>
        <div class="meta">{{ __('body.generated_at') }}: {{ $generatedAt }}</div>
    </div>

    <div class="section">
        <div class="section-title">{{ __('body.booking') }}</div>
        <table>
            <tr>
                <td class="label">{{ __('body.status') }}</td>
                <td class="value">{{ $booking->status->label() }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.customer') }}</td>
                <td class="value">{{ $booking->customerName() }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.vehicle') }}</td>
                <td class="value">{{ $booking->assignedVehicle?->name() ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.category') }}</td>
                <td class="value">{{ $booking->category?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.daily_rate') }}</td>
                <td class="value">{{ number_format((float) $booking->category_daily_price, 0) }} RWF/{{ __('body.day') }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.rental_type') }}</td>
                <td class="value">{{ $booking->rental_type?->label() ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.pickup_datetime') }}</td>
                <td class="value">{{ \App\Libraries\GeneralLibrary::formatDateTime($booking->pickup_at) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.return_datetime') }}</td>
                <td class="value">{{ \App\Libraries\GeneralLibrary::formatDateTime($booking->return_at) }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.pickup_method') }}</td>
                <td class="value">{{ $booking->pickup_method?->label() ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.pickup_location') }}</td>
                <td class="value">
                    @if ($booking->pickup_method === \App\Enums\PickupMethod::Delivery)
                        {{ $booking->delivery_address ?: '—' }}
                    @else
                        {{ $booking->pickupStation?->name ?? '—' }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">{{ __('body.return_method') }}</td>
                <td class="value">{{ $booking->return_method?->label() ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('body.return_station') }}</td>
                <td class="value">
                    @if ($booking->return_method === \App\Enums\ReturnMethod::Collection)
                        {{ $booking->collection_address ?: '—' }}
                    @else
                        {{ $booking->returnStation?->name ?? '—' }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">{{ __('body.payment_option') }}</td>
                <td class="value">{{ $booking->payment_option?->label() ?? '—' }}</td>
            </tr>
        </table>
    </div>

    @if ($booking->extras->isNotEmpty())
        <div class="section">
            <div class="section-title">{{ __('body.extras') }}</div>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('body.extras') }}</th>
                        <th>{{ __('body.quantity') }}</th>
                        <th style="text-align:right;">{{ __('body.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($booking->extras as $line)
                        <tr>
                            <td>{{ $line->extra?->name }}</td>
                            <td>{{ $line->quantity }}</td>
                            <td style="text-align:right;">{{ number_format((float) $line->total, 0) }} RWF</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="totals">
        @php
            $rentalSubtotal = bcmul((string) ($booking->category_daily_price ?? 0), (string) max(1, (int) $booking->rental_days), 2);
        @endphp
        <table>
            <tr>
                <td class="label">{{ __('body.rental_days') }} ({{ number_format((float) $booking->category_daily_price, 0) }} × {{ (int) $booking->rental_days }})</td>
                <td class="value" style="text-align:right;">{{ number_format((float) $rentalSubtotal, 0) }} RWF</td>
            </tr>
            @if ((float) ($booking->delivery_fee ?? 0) > 0)
                <tr>
                    <td class="label">{{ __('body.delivery_fee') }}</td>
                    <td class="value" style="text-align:right;">{{ number_format((float) $booking->delivery_fee, 0) }} RWF</td>
                </tr>
            @endif
            @if ((float) ($booking->collection_fee ?? 0) > 0)
                <tr>
                    <td class="label">{{ __('body.collection_fee') }}</td>
                    <td class="value" style="text-align:right;">{{ number_format((float) $booking->collection_fee, 0) }} RWF</td>
                </tr>
            @endif
            @if ((float) ($booking->chauffeur_fee ?? 0) > 0)
                <tr>
                    <td class="label">{{ __('body.chauffeur_fee') }}</td>
                    <td class="value" style="text-align:right;">{{ number_format((float) $booking->chauffeur_fee, 0) }} RWF</td>
                </tr>
            @endif
            @if ((float) ($booking->extras_total ?? 0) > 0)
                <tr>
                    <td class="label">{{ __('body.extras') }}</td>
                    <td class="value" style="text-align:right;">{{ number_format((float) $booking->extras_total, 0) }} RWF</td>
                </tr>
            @endif
            <tr>
                <td class="label grand">{{ __('body.total') }}</td>
                <td class="value grand" style="text-align:right;">{{ number_format((float) $booking->total_amount, 0) }} RWF</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        {{ __('body.booking_receipt_footer', ['company' => $company]) }}
    </div>
</body>
</html>
