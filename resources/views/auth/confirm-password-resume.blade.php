{{-- Re-submits the action that asked for password confirmation. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- No referer, so the action's back() uses the page the user started on. --}}
    <meta name="referrer" content="no-referrer">
    <x-common.brand-head :title="__('body.confirm_password_title')" />
    <style>body{font-family:Inter,system-ui,sans-serif;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;color:#6B7280}</style>
</head>
<body>
    <form id="resume-action" action="{{ $url }}" method="POST">
        @csrf
        @if (strtoupper($method) !== 'POST')
            <input type="hidden" name="_method" value="{{ $method }}">
        @endif
        @foreach ($fields as [$name, $value])
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <p>{{ __('body.confirm_password_continuing') }}</p>
        <noscript><button type="submit">{{ __('body.continue') }}</button></noscript>
    </form>
    <script>document.getElementById('resume-action').submit();</script>
</body>
</html>
