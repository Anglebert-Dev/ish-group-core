{{--
    The one printed document (docs/branding-print-build-guide.md §4).
    Rendered by dompdf ($mode = pdf) and by browsers ($mode = html), so it
    sticks to what dompdf supports: tables, floats, fixed boxes. No flex/grid.
--}}
@php
    $layout = $design->layout;
    $accent = $design->accent;
    $compact = $layout === 'compact';
    $base = $compact ? 9 : 10;
    $isHtml = $mode === 'html';
    $paperSizes = ['a4' => 'A4', 'letter' => 'letter', 'a5' => 'A5'];
    $numbered = count($doc->columns) > 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim($doc->title.' '.($doc->number ?? '')) }} — {{ $brand->name }}</title>
    <style>
        @page { size: {{ $paperSizes[$design->paper] ?? 'A4' }} {{ $doc->orientation }}; margin: {{ $compact ? '12mm 12mm 18mm' : '16mm 15mm 22mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: {!! $design->fontStack() !!}; font-size: {{ $base }}px; line-height: 1.45; color: #1F2937; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .muted { color: #6B7280; }
        .accent { color: {{ $accent }}; }
        .right { text-align: right; }
        .center { text-align: center; }
        .nowrap { white-space: nowrap; }
        .pre { white-space: pre-line; }

        /* Header */
        .brand-name { font-size: {{ $compact ? 13 : 16 }}px; font-weight: bold; color: {{ $layout === 'modern' ? '#FFFFFF' : $design->accent }}; }
        .brand-note { font-size: {{ $base - 1 }}px; margin-top: 2px; }
        .contact { font-size: {{ $base - 1.5 }}px; margin-top: 4px; }
        .doc-title { font-size: {{ $compact ? 16 : 22 }}px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; color: {{ $layout === 'modern' ? '#FFFFFF' : '#111827' }}; }
        .doc-number { font-size: {{ $base + 2 }}px; font-weight: bold; margin-top: 2px; }
        .band { background: {{ $accent }}; color: #FFFFFF; padding: {{ $compact ? '10px 12px' : '16px 18px' }}; }
        .band .muted, .band .contact { color: {{ $accentLight }}; }
        .rule { border-bottom: 2px solid {{ $accent }}; padding-bottom: {{ $compact ? 8 : 12 }}px; }
        .logo { height: {{ $design->logoHeight() }}px; max-width: 220px; }

        /* Meta and parties */
        .meta td { padding: 1px 0; }
        .meta td.label { color: #6B7280; padding-right: 10px; }
        .parties { margin-top: {{ $compact ? 10 : 16 }}px; }
        .party { padding: {{ $compact ? '6px 8px' : '10px 12px' }}; background: {{ $layout === 'classic' ? '#F9FAFB' : $accentLight }}; border-left: 3px solid {{ $accent }}; }
        .party-label { font-size: {{ $base - 2 }}px; text-transform: uppercase; letter-spacing: 0.6px; color: {{ $accentDark }}; font-weight: bold; }
        .party-name { font-weight: bold; font-size: {{ $base + 1 }}px; margin: 2px 0; }
        .meta-boxes td { padding: 6px 10px; border: 1px solid #E5E7EB; }
        .meta-boxes .label { font-size: {{ $base - 2 }}px; text-transform: uppercase; color: #6B7280; letter-spacing: 0.5px; }

        /* Items */
        .items { margin-top: {{ $compact ? 10 : 18 }}px; }
        .items th { padding: {{ $compact ? '4px 6px' : '7px 8px' }}; font-size: {{ $base - 1 }}px; text-transform: uppercase; letter-spacing: 0.4px; text-align: left;
            @if ($compact) border-bottom: 1.5px solid {{ $accent }}; color: {{ $accentDark }}; @else background: {{ $accent }}; color: #FFFFFF; @endif }
        .items td { padding: {{ $compact ? '3px 6px' : '6px 8px' }}; border-bottom: 1px solid #E5E7EB; }
        .items tr.even td { background: {{ $layout === 'modern' ? $accentLight : '#F9FAFB' }}; }
        .items .empty { text-align: center; color: #6B7280; padding: 18px; }

        /* Totals */
        .totals { width: {{ $compact ? '40%' : '45%' }}; margin-top: 8px; }
        .totals td { padding: {{ $compact ? '3px 6px' : '5px 8px' }}; }
        .totals tr.strong td { font-weight: bold; font-size: {{ $base + 1.5 }}px; background: {{ $accentLight }}; color: {{ $accentDark }}; border-top: 2px solid {{ $accent }}; }

        /* Blocks */
        .block { margin-top: {{ $compact ? 10 : 16 }}px; }
        .block-title { font-size: {{ $base - 1 }}px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: {{ $accentDark }}; margin-bottom: 3px; }
        .pay td { padding: 4px 8px; border: 1px solid #E5E7EB; font-size: {{ $base - 0.5 }}px; }
        .sign { width: 46%; float: right; text-align: center; margin-top: {{ $compact ? 14 : 24 }}px; page-break-inside: avoid; }
        .sign-images { height: 72px; position: relative; }
        .sign-images img.stamp { height: 72px; opacity: 0.85; }
        .sign-images img.signature { height: 48px; }
        .sign-line { border-top: 1px solid #9CA3AF; margin-top: 4px; padding-top: 4px; }
        .clear { clear: both; }

        /* Watermark and footer (fixed: repeated on every PDF page) */
        .watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 96px; font-weight: bold; letter-spacing: 6px;
            color: {{ $accent }}; opacity: 0.08; transform: rotate(-28deg); z-index: -1; }
        .footer { font-size: {{ $base - 2 }}px; color: #6B7280; border-top: 1px solid #E5E7EB; padding-top: 5px; }
        @if (! $isHtml)
            .footer { position: fixed; bottom: -14mm; left: 0; right: 0; }
        @endif

        @if ($isHtml)
            /* The print view: a sheet on screen, plain paper when printed. */
            body { background: #E5E7EB; }
            .sheet { background: #FFFFFF; max-width: {{ $doc->orientation === 'landscape' ? '1120px' : '820px' }}; margin: 24px auto; padding: 40px 44px; box-shadow: 0 1px 3px rgba(0,0,0,.12); position: relative; overflow: hidden; }
            .toolbar { max-width: {{ $doc->orientation === 'landscape' ? '1120px' : '820px' }}; margin: 16px auto 0; text-align: right; }
            .toolbar a, .toolbar button { display: inline-block; font: 600 13px/1 system-ui, sans-serif; padding: 9px 14px; border-radius: 8px; border: 1px solid #D1D5DB; background: #FFFFFF; color: #111827; text-decoration: none; cursor: pointer; margin-left: 6px; }
            .toolbar .primary { background: {{ $accent }}; border-color: {{ $accent }}; color: #FFFFFF; }
            .footer { margin-top: 28px; }
            .watermark { position: absolute; }
            @media print {
                body { background: #FFFFFF; }
                .toolbar { display: none; }
                .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; overflow: visible; }
                .watermark { position: fixed; }
                .items tr { page-break-inside: avoid; }
            }
            @media (max-width: 640px) { .sheet { padding: 20px 16px; margin: 12px 8px; } }
        @endif
    </style>
</head>
<body>
@if ($isHtml)
    <div class="toolbar">
        @if (! empty($pdfUrl))<a href="{{ $pdfUrl }}">{{ __('body.print_download_pdf') }}</a>@endif
        <button type="button" class="primary" onclick="window.print()">{{ __('body.print') }}</button>
    </div>
    <div class="sheet">
@endif

@if ($doc->watermark && $design->showWatermark)
    <div class="watermark">{{ $doc->watermark }}</div>
@endif

{{-- Footer first so dompdf repeats it on every page --}}
<div class="footer">
    <table><tr>
        <td>
            @if ($footer)<span class="pre">{{ $footer }}</span>@else{{ $brand->documentName() }}@endif
        </td>
        <td class="right nowrap">
            {{ __('body.print_printed', ['time' => $printedAt]) }}@unless ($isHtml)<span style="display: inline-block; width: 70px"></span>@endunless
        </td>
    </tr></table>
</div>

{{-- Header --}}
@php
    $b = ['logo' => $logo, 'name' => $brand->documentName(), 'note' => $design->headerNote, 'contact' => $design->showContact ? $brand->contactLines() : []];
    $titleBlock = '<div class="doc-title">'.e($doc->title).'</div>'.($doc->number ? '<div class="doc-number">'.e($doc->number).'</div>' : '');
@endphp

@if ($layout === 'modern')
    <div class="band">
        <table><tr>
            <td style="width: 60%">
                @if ($b['logo'])<img class="logo" src="{{ $b['logo'] }}" alt=""><br>@endif
                <div class="brand-name">{{ $b['name'] }}</div>
                @if ($b['note'])<div class="brand-note muted">{{ $b['note'] }}</div>@endif
                @foreach ($b['contact'] as $line)<div class="contact pre">{{ $line }}</div>@endforeach
            </td>
            <td class="right">{!! $titleBlock !!}</td>
        </tr></table>
    </div>
    @if ($doc->meta)
        <table class="meta-boxes" style="margin-top: 10px"><tr>
            @foreach ($doc->meta as $item)
                <td><div class="label">{{ $item['label'] }}</div><div><strong>{{ $item['value'] }}</strong></div></td>
            @endforeach
        </tr></table>
    @endif
@else
    <div class="rule">
        @if ($design->logoPosition === 'center')
            <div class="center">
                @if ($b['logo'])<img class="logo" src="{{ $b['logo'] }}" alt=""><br>@endif
                <div class="brand-name">{{ $b['name'] }}</div>
                @if ($b['note'])<div class="brand-note muted">{{ $b['note'] }}</div>@endif
                @foreach ($b['contact'] as $line)<div class="contact muted pre">{{ $line }}</div>@endforeach
            </div>
            <table style="margin-top: 10px"><tr>
                <td>{!! $titleBlock !!}</td>
                <td class="right">
                    <table class="meta" style="width: auto; margin-left: auto">
                        @foreach ($doc->meta as $item)<tr><td class="label right">{{ $item['label'] }}</td><td class="right"><strong>{{ $item['value'] }}</strong></td></tr>@endforeach
                    </table>
                </td>
            </tr></table>
        @else
            @php $logoRight = $design->logoPosition === 'right'; @endphp
            <table><tr>
                <td style="width: 55%" class="{{ $logoRight ? 'right' : '' }}">
                    @if ($logoRight)
                        {!! $titleBlock !!}
                        <table class="meta" style="width: auto; margin-top: 6px">
                            @foreach ($doc->meta as $item)<tr><td class="label">{{ $item['label'] }}</td><td><strong>{{ $item['value'] }}</strong></td></tr>@endforeach
                        </table>
                    @else
                        @if ($b['logo'])<img class="logo" src="{{ $b['logo'] }}" alt=""><br>@endif
                        <div class="brand-name">{{ $b['name'] }}</div>
                        @if ($b['note'])<div class="brand-note muted">{{ $b['note'] }}</div>@endif
                        @foreach ($b['contact'] as $line)<div class="contact muted pre">{{ $line }}</div>@endforeach
                    @endif
                </td>
                <td class="right">
                    @if ($logoRight)
                        @if ($b['logo'])<img class="logo" src="{{ $b['logo'] }}" alt=""><br>@endif
                        <div class="brand-name">{{ $b['name'] }}</div>
                        @if ($b['note'])<div class="brand-note muted">{{ $b['note'] }}</div>@endif
                        @foreach ($b['contact'] as $line)<div class="contact muted pre">{{ $line }}</div>@endforeach
                    @else
                        {!! $titleBlock !!}
                        <table class="meta" style="width: auto; margin-left: auto; margin-top: 6px">
                            @foreach ($doc->meta as $item)<tr><td class="label right">{{ $item['label'] }}</td><td class="right"><strong>{{ $item['value'] }}</strong></td></tr>@endforeach
                        </table>
                    @endif
                </td>
            </tr></table>
        @endif
    </div>
@endif

{{-- Parties --}}
@if ($doc->parties)
    <table class="parties"><tr>
        @foreach ($doc->parties as $i => $party)
            @if ($i > 0)<td style="width: 4%"></td>@endif
            <td class="party" style="width: {{ floor(96 / count($doc->parties)) }}%">
                <div class="party-label">{{ $party['label'] }}</div>
                <div class="party-name">{{ $party['name'] }}</div>
                @foreach ($party['lines'] as $line)<div class="pre">{{ $line }}</div>@endforeach
            </td>
        @endforeach
    </tr></table>
@endif

{{-- Items --}}
@if ($numbered)
    <table class="items">
        <thead><tr>
            @foreach ($doc->columns as $column)
                <th style="text-align: {{ $column['align'] }}">{{ $column['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @forelse ($doc->rows as $r => $row)
                <tr class="{{ $r % 2 ? 'even' : '' }}">
                    @foreach ($row as $c => $cell)
                        <td style="text-align: {{ $doc->columns[$c]['align'] ?? 'left' }}" class="{{ ($doc->columns[$c]['align'] ?? 'left') === 'right' ? 'nowrap' : '' }}">{!! nl2br(e($cell)) !!}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($doc->columns) }}">{{ $doc->emptyText ?? __('body.no_records_to_export') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

{{-- Totals --}}
@if ($doc->totals)
    <table class="totals" style="margin-left: auto">
        @foreach ($doc->totals as $total)
            <tr class="{{ $total['strong'] ? 'strong' : '' }}"><td>{{ $total['label'] }}</td><td class="right nowrap">{{ $total['value'] }}</td></tr>
        @endforeach
    </table>
@endif

{{-- Extra sections (delivery receiver, measurements, …) --}}
@foreach ($doc->sections as $section)
    <div class="block">
        <div class="block-title">{{ $section['title'] }}</div>
        @foreach ($section['lines'] as $line)<div class="pre">{{ $line }}</div>@endforeach
        @if ($section['image'])<img src="{{ $section['image'] }}" alt="" style="height: 64px; margin-top: 4px">@endif
    </div>
@endforeach

@if ($doc->notes)
    <div class="block"><div class="block-title">{{ __('body.print_notes') }}</div><div class="pre">{{ $doc->notes }}</div></div>
@endif

{{-- How to pay --}}
@if ($doc->payment && $design->showPayment && $brand->paymentMethods)
    <div class="block">
        <div class="block-title">{{ __('body.print_how_to_pay') }}</div>
        <table class="pay">
            @foreach ($brand->paymentMethods as $method)
                <tr>
                    <td style="width: 26%"><strong>{{ $method['label'] ?: ($method['provider'] ?: __('body.brand_payment_'.$method['type'])) }}</strong></td>
                    <td>
                        {{ implode(' · ', array_filter([$method['provider'] && $method['label'] ? $method['provider'] : null, $method['account_name'], $method['number'], $method['branch'] ? __('body.brand_payment_branch').' '.$method['branch'] : null, $method['swift'] ? 'SWIFT '.$method['swift'] : null])) }}
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endif

@if ($terms)
    <div class="block"><div class="block-title">{{ __('body.print_terms') }}</div><div class="pre muted">{{ $terms }}</div></div>
@endif

{{-- Stamp and signature --}}
@if ($doc->signature && ($design->showSignature || $design->showStamp))
    <div class="sign">
        <div class="sign-images">
            @if ($stamp)<img class="stamp" src="{{ $stamp }}" alt="">@endif
            @if ($signature)<img class="signature" src="{{ $signature }}" alt="">@endif
        </div>
        <div class="sign-line">
            <strong>{{ $brand->signatoryName ?: __('body.print_authorised_signature') }}</strong>
            @if ($brand->signatoryTitle)<br><span class="muted">{{ $brand->signatoryTitle }}</span>@endif
            @if ($brand->signatoryName)<br><span class="muted">{{ __('body.print_authorised_signature') }}</span>@endif
        </div>
    </div>
    <div class="clear"></div>
@endif

@if ($isHtml)
    </div>
    @if (! empty($autoPrint))
        <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
    @endif
@endif
</body>
</html>
