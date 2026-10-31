{{-- Standalone on purpose: no components that read branding or the database. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('install.title') }} · {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: {
            brand: { DEFAULT: '#124EB0', dark: '#0d3a85' },
            ink: '#111827', muted: '#6B7280', line: '#E5E7EB', success: '#16A34A', danger: '#DC2626', warning: '#F59E0B',
        }}}};
    </script>
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .field { display:block; width:100%; height:2.75rem; border:1px solid #E5E7EB; border-radius:.75rem; padding:0 .875rem; font-size:15px; background:#fff; }
        .field:focus { outline:2px solid #124EB0; outline-offset:-1px; }
        .label { display:block; font-size:13px; font-weight:600; color:#111827; margin-bottom:.375rem; }
        .hint { font-size:12px; color:#6B7280; margin-top:.25rem; }
        .btn { display:inline-flex; align-items:center; justify-content:center; height:2.75rem; padding:0 1.25rem; border-radius:.75rem; font-weight:600; font-size:15px; }
        .btn-primary { background:#124EB0; color:#fff; } .btn-primary:hover { background:#0d3a85; }
        .btn-light { background:#fff; color:#111827; border:1px solid #E5E7EB; } .btn-light:hover { background:#F9FAFB; }
        code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    </style>
</head>
<body class="bg-gray-100 text-ink min-h-screen">
<div class="max-w-4xl mx-auto px-4 py-8 sm:py-14">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ __('install.title') }} {{ config('app.name') }}</h1>
        <p class="text-muted text-sm mt-1">{{ __('install.subtitle') }}</p>
    </div>

    <div class="grid gap-6 md:grid-cols-[200px_1fr]">
        <ol class="flex md:flex-col gap-2 overflow-x-auto text-sm">
            @foreach ($steps as $i => $step)
                @php
                    $index = $current ? array_search($current, $steps, true) : count($steps);
                    $state = $i < $index ? 'done' : ($i === $index ? 'current' : 'todo');
                @endphp
                <li class="flex items-center gap-2 whitespace-nowrap {{ $state === 'current' ? 'font-semibold text-ink' : ($state === 'done' ? 'text-success' : 'text-muted') }}">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs {{ $state === 'current' ? 'bg-brand text-white' : ($state === 'done' ? 'bg-green-100' : 'bg-white border border-line') }}">
                        {{ $state === 'done' ? '✓' : $i + 1 }}
                    </span>
                    {{ __('install.step_'.$step) }}
                </li>
            @endforeach
        </ol>

        <main class="bg-white rounded-2xl border border-line p-5 sm:p-7 shadow-sm">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-50 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
