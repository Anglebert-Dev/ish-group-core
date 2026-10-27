<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $export->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        .header { margin-bottom: 16px; }
        .header img { height: 42px; max-width: 220px; margin-bottom: 8px; }
        .company { font-size: 14px; font-weight: bold; color: {{ $brandColor }}; }
        .title { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .meta { font-size: 9px; color: #667085; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: {{ $brandColor }}; color: #fff; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .empty { text-align: center; padding: 24px; color: #667085; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoPath)
            <img src="{{ $logoPath }}" alt="">
        @endif
        <div class="company">{{ $company }}</div>
        <div class="title">{{ $export->title }}</div>
        <div class="meta">{{ __('body.generated_at') }}: {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($export->headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($export->rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ max(1, count($export->headings)) }}">{{ __('body.no_records_to_export') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
