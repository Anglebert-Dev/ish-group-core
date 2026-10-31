{{-- Sent to the customer: standalone and printable (Print → Save as PDF). --}}
@php
    $d = fn (string $key) => \Modules\Distribution\Support\ServerDetails::display($key, $details[$key] ?? '') ?: null;
    $folder = $details['install_path'] ?? '/path/to/the/app';
    $publicRoot = ($details['web_root_is_public'] ?? true) !== false;
    $cronOnly = $details['cron_only'] ?? ($details['hosting_type'] ?? null) === 'shared';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('distribution::distribution.guide_title', ['site' => $site->name]) }}</title>
    <style>
        :root { --ink: #111827; --muted: #6B7280; --line: #E5E7EB; --brand: #124EB0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F3F4F6; color: var(--ink); font: 15px/1.6 Inter, ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 820px; margin: 32px auto; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 40px 44px; }
        h1 { font-size: 26px; line-height: 1.25; margin: 0 0 8px; }
        h2 { font-size: 18px; margin: 32px 0 10px; padding-top: 20px; border-top: 1px solid var(--line); }
        p, li { color: #1F2937; }
        .muted { color: var(--muted); }
        ul { padding-left: 20px; }
        li { margin: 4px 0; }
        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        pre { background: #0F172A; color: #E2E8F0; border-radius: 10px; padding: 12px 14px; white-space: pre-wrap; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        td { border-bottom: 1px solid var(--line); padding: 6px 4px; vertical-align: top; }
        td:first-child { color: var(--muted); width: 42%; }
        .problems { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; border-radius: 10px; padding: 12px 16px; }
        .print { float: right; background: var(--brand); color: #fff; border: 0; border-radius: 10px; padding: 9px 16px; font-weight: 600; cursor: pointer; }
        @media (max-width: 640px) { main { margin: 0; border-radius: 0; padding: 24px 16px; } .print { float: none; margin-bottom: 16px; } }
        @media print { body { background: #fff; } main { margin: 0; border: 0; padding: 0; } .print { display: none; } h2 { break-after: avoid; } pre { background: #F3F4F6; color: #111827; border: 1px solid var(--line); } }
    </style>
</head>
<body>
<main>
    <button class="print" onclick="window.print()">{{ __('distribution::distribution.guide_print') }}</button>
    <h1>{{ __('distribution::distribution.guide_title', ['site' => $site->name]) }}</h1>
    <p class="muted">{{ $site->customer_name }}</p>
    <p>{{ __('distribution::distribution.guide_intro', ['provider' => $provider]) }}</p>

    @if ($problems)
        <div class="problems">
            <strong>{{ __('distribution::distribution.guide_problems') }}</strong>
            <ul>@foreach ($problems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
        </div>
    @endif

    <h2>{{ __('distribution::distribution.guide_step_form') }}</h2>
    <p>{{ __('distribution::distribution.guide_step_form_text') }}</p>

    <h2>{{ __('distribution::distribution.guide_step_requirements') }}</h2>
    <ul>
        <li>{{ __('distribution::distribution.guide_req_php', ['min' => $minPhp, 'extensions' => implode(', ', $extensions)]) }}</li>
        <li>{{ __('distribution::distribution.guide_req_db') }}</li>
        <li>{{ __('distribution::distribution.guide_req_disk') }}</li>
        <li>{{ __('distribution::distribution.guide_req_https') }}</li>
        <li>{{ __('distribution::distribution.guide_req_cron') }}</li>
        <li>{{ __('distribution::distribution.guide_req_email') }}</li>
        <li>{{ __('distribution::distribution.guide_req_composer') }}</li>
    </ul>

    <h2>{{ __('distribution::distribution.guide_step_install') }}</h2>
    <ol>
        <li>{{ __('distribution::distribution.guide_install_upload') }} <code>{{ $folder }}</code></li>
        <li>
            @if ($publicRoot)
                {{ __('distribution::distribution.guide_install_root_public') }} <code>{{ rtrim($folder, '/') }}/public</code>
            @else
                {{ __('distribution::distribution.guide_install_root_folder') }}
            @endif
        </li>
        <li>{{ __('distribution::distribution.guide_install_writable') }}</li>
        <li>{{ __('distribution::distribution.guide_install_open') }} @if ($d('app_url'))<code>{{ $d('app_url') }}</code>@endif</li>
    </ol>

    <h2>{{ __('distribution::distribution.guide_step_after') }}</h2>
    <p>{{ __('distribution::distribution.guide_after_cron') }}</p>
    <pre>{{ $cronLine }}</pre>
    @if ($cronOnly)
        <p>{{ __('distribution::distribution.guide_after_queue_cron') }}</p>
    @else
        <p>{{ __('distribution::distribution.guide_after_queue_worker') }}</p>
        <pre>cd {{ $folder }} && {{ $details['php_path'] ?? 'php' }} artisan queue:work --tries=3</pre>
    @endif
    <p>{{ __('distribution::distribution.guide_after_updates', ['provider' => $provider]) }}</p>

    <h2>{{ __('distribution::distribution.guide_your_answers') }}</h2>
    <table>
        @foreach (array_keys($fields) as $key)
            <tr>
                <td>{{ __('distribution::distribution.server_'.$key) }}</td>
                <td>{!! $d($key) !== null ? e($d($key)) : '<span class="muted">'.e(__('distribution::distribution.guide_not_given')).'</span>' !!}</td>
            </tr>
        @endforeach
    </table>
</main>
</body>
</html>
