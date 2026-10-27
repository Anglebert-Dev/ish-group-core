<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-common.brand-head :title="__('body.access_denied')" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { display: ['Inter', 'sans-serif'], sans: ['Inter', 'sans-serif'] },
            colors: {
                brand: { DEFAULT: 'var(--brand)', dark: 'var(--brand-dark)', light: 'var(--brand-light)' },
                ink: '#111827', body: '#1F2937', muted: '#6B7280', surface: '#F8FAFC', line: '#E5E7EB',
                danger: '#DC2626',
            },
        }}};
    </script>
    <style>body { font-family: Inter, sans-serif; }</style>
</head>
<body class="bg-surface text-body antialiased min-h-screen flex items-center justify-center px-6">
    <div class="max-w-sm w-full text-center">
        <div class="mx-auto h-16 w-16 rounded-full bg-danger/10 flex items-center justify-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <p class="font-display font-extrabold text-5xl text-ink">403</p>
        <h1 class="font-display font-bold text-xl text-ink mt-3">{{ __('body.access_denied') }}</h1>
        <p class="text-sm text-muted mt-2 leading-relaxed">
            {{ $exception->getMessage() ?: __('body.no_permission') }}
        </p>
        <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}"
           class="inline-flex items-center justify-center gap-2 font-semibold rounded-lg transition-colors bg-brand text-white hover:bg-brand-dark text-[15px] px-5 py-2.5 mt-6">
            {{ __('body.back_to_dashboard') }}
        </a>
    </div>
</body>
</html>
