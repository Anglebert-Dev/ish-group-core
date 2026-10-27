<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#F3F4F6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1F2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F4F6;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:12px;overflow:hidden;border:1px solid #E5E7EB;">
                <tr>
                    <td style="background:{{ $brand['color'] }};padding:20px 28px;">
                        @if ($brand['logo'])
                            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" style="max-height:36px;">
                        @else
                            <span style="color:#FFFFFF;font-size:18px;font-weight:700;">{{ $brand['name'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 16px;font-size:20px;color:#111827;">{{ $subject }}</h1>
                        @foreach ($lines as $line)
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">{!! nl2br(e($line)) !!}</p>
                        @endforeach
                        @if ($actionText && $actionUrl)
                            <p style="margin:24px 0;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:{{ $brand['color'] }};color:#FFFFFF;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:8px;">{{ $actionText }}</a>
                            </p>
                        @endif
                        @foreach ($outro as $line)
                            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#6B7280;">{!! nl2br(e($line)) !!}</p>
                        @endforeach
                        @if ($actionText && $actionUrl)
                            <p style="margin:20px 0 0;font-size:12px;color:#9CA3AF;word-break:break-all;">{{ __('body.email_link_fallback') }} {{ $actionUrl }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;border-top:1px solid #F3F4F6;font-size:12px;color:#9CA3AF;">&copy; {{ date('Y') }} {{ $brand['name'] }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
