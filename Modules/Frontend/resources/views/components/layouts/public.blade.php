@props(['title' => null, 'panel' => false, 'rawTitle' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <x-common.brand-head :title="$title ?? null" :full-title="$rawTitle" />
    @if (filled($site->get('general.seo_description')))
        <meta name="description" content="{{ $site->get('general.seo_description') }}">
    @endif

    <x-common.tailwind />

    <style>
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body); }
        .font-display { font-family: var(--font-body); }
        .hero-glow {
            background: linear-gradient(180deg, #071422 0%, #030910 55%, #010409 100%);
        }
        @keyframes form-wizard-step-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-wizard-step-panel:not([hidden]) {
            animation: form-wizard-step-in 0.28s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        @media (prefers-reduced-motion: reduce) {
            .form-wizard-step-panel:not([hidden]) { animation: none; }
            .brand-marquee-track { animation: none !important; }
        }
        .brand-marquee {
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }
        .brand-marquee-track {
            display: flex;
            align-items: center;
            gap: 3rem;
            width: max-content;
            animation: brand-marquee-scroll 72s linear infinite;
        }
        .brand-marquee:hover .brand-marquee-track {
            animation-play-state: paused;
        }
        .brand-marquee-item {
            flex: 0 0 auto;
            min-width: 7.5rem;
            color: rgb(var(--c-ink));
        }
        @keyframes brand-marquee-scroll {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        /* Landing fixed navbar — transparent over hero, solid after scroll */
        .landing-nav[data-solid="false"] {
            background: transparent;
            border-bottom: 1px solid transparent;
        }
        .landing-nav[data-solid="true"] {
            background: rgb(var(--c-card) / 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgb(var(--c-line));
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .landing-nav[data-solid="false"] .landing-nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        .landing-nav[data-solid="false"] .landing-nav-link:hover,
        .landing-nav[data-solid="false"] .landing-nav-link.is-active {
            color: #fff;
        }
        .landing-nav[data-solid="true"] .landing-nav-link {
            color: rgb(var(--c-body));
        }
        .landing-nav[data-solid="true"] .landing-nav-link:hover {
            color: var(--brand);
        }
        .landing-nav[data-solid="true"] .landing-nav-link.is-active {
            color: var(--brand);
        }
        .landing-nav[data-solid="false"] .landing-nav-muted {
            color: rgba(255, 255, 255, 0.8);
        }
        .landing-nav[data-solid="false"] .landing-nav-muted:hover {
            color: #fff;
        }
        .landing-nav[data-solid="true"] .landing-nav-muted {
            color: rgb(var(--c-body));
        }
        .landing-nav[data-solid="true"] .landing-nav-muted:hover {
            color: var(--brand);
        }
        .landing-nav[data-solid="false"] .landing-nav-login {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .landing-nav[data-solid="false"] .landing-nav-login:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .landing-nav[data-solid="true"] .landing-nav-login {
            color: rgb(var(--c-body));
            border: 1px solid transparent;
        }
        .landing-nav[data-solid="true"] .landing-nav-login:hover {
            color: var(--brand);
        }
        .landing-nav[data-solid="false"] .landing-nav-toggle {
            color: #fff;
        }
        .landing-nav[data-solid="true"] .landing-nav-toggle {
            color: rgb(var(--c-ink));
        }
    </style>
</head>
<body class="{{ $panel ? 'bg-surface' : 'bg-card' }} text-body antialiased">

    <x-frontend::navbar />

    @if ($panel)
        <div class="min-h-screen lg:flex">
            <x-frontend::account-nav />

            <div class="flex-1 min-w-0 lg:pl-64">
                <main class="p-4 sm:p-5 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    @else
        <main>
            {{ $slot }}
        </main>

        <x-frontend::footer />
    @endif

    <x-common.toast />
    <x-common.confirm-modal />
    <script src="{{ asset('js/datetime-split.js') }}"></script>
    <script src="{{ asset('js/searchable-select.js') }}"></script>
    <script src="{{ asset('js/form-wizard.js') }}"></script>
    @stack('scripts')
</body>
</html>
