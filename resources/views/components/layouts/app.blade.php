@php $appLayout = app(\App\Services\Branding\BrandingService::class)->layout($branding); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {!! $appLayout->attributes() !!}>
<head>
    {{-- Collapsed sidebar: this person's choice (this browser), else the business's start state. Before paint, so nothing jumps. --}}
    <script>(function () { try { var saved = localStorage.getItem('sidebar-collapsed'); if (saved === '1' || (saved === null && document.documentElement.dataset.sidebarStart === 'collapsed')) document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {} })();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <x-common.brand-head :title="$title ?? 'Dashboard'" />

    <x-common.tailwind />
    <style>
        body { font-family: var(--font-body); }
        .font-display { font-family: var(--font-body); }
        @keyframes form-wizard-step-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-wizard-step-panel:not([hidden]) {
            animation: form-wizard-step-in 0.28s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        @media (prefers-reduced-motion: reduce) {
            .form-wizard-step-panel:not([hidden]) { animation: none; }
        }
    </style>
</head>
<body class="bg-page text-body antialiased">

    <div class="min-h-screen lg:flex">

        <x-dashboard.sidebar />

        <div class="app-main flex-1 min-w-0 lg:pl-[var(--sidebar-w)]">
            <x-dashboard.impersonation-banner />
            <x-dashboard.preview-banner />
            <x-dashboard.header :title="$title ?? 'Dashboard'" />
            <x-dashboard.announcement />

            <main class="app-content p-4 sm:p-5 lg:p-8">
                <div class="mx-auto w-full max-w-[var(--content-max)]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar(forceOpen) {
            var sidebar = document.getElementById('dashboard-sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            if (!sidebar || !backdrop) return;

            var desktop = window.matchMedia('(min-width: 1024px)').matches;
            if (desktop) {
                sidebar.classList.remove('-translate-x-full');
                backdrop.classList.add('hidden');
                document.body.classList.remove('nav-locked');
                sidebar.setAttribute('aria-hidden', 'false');
                return;
            }

            var currentlyOpen = !sidebar.classList.contains('-translate-x-full');
            var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !currentlyOpen;

            sidebar.classList.toggle('-translate-x-full', !shouldOpen);
            backdrop.classList.toggle('hidden', !shouldOpen);
            document.body.classList.toggle('nav-locked', shouldOpen);
            sidebar.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
        }

        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('dashboard-sidebar');
            if (!sidebar) return;

            var nav = document.getElementById('dashboard-sidebar-nav');
            var scrollKey = 'dashboard-sidebar-scroll';

            if (nav) {
                try {
                    var saved = sessionStorage.getItem(scrollKey);
                    if (saved !== null) {
                        nav.scrollTop = parseInt(saved, 10) || 0;
                    }
                } catch (e) {}

                var persistScroll = function () {
                    try {
                        sessionStorage.setItem(scrollKey, String(nav.scrollTop));
                    } catch (e) {}
                };

                nav.addEventListener('scroll', persistScroll, { passive: true });

                sidebar.querySelectorAll('a[href]').forEach(function (link) {
                    link.addEventListener('click', persistScroll);
                });
            }

            sidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (window.matchMedia('(max-width: 1023px)').matches) {
                        toggleSidebar(false);
                    }
                });
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggleSidebar(false);
        });

        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                toggleSidebar(false);
            }
        });
    </script>

    <x-common.toast />
    <x-common.confirm-modal />
    <x-common.password-confirm-modal />
    <script src="{{ asset('js/datetime-split.js') }}"></script>
    <script src="{{ asset('js/searchable-select.js') }}{{ file_exists(public_path('js/searchable-select.js')) ? '?v='.filemtime(public_path('js/searchable-select.js')) : '' }}"></script>
    <script src="{{ asset('js/form-wizard.js') }}{{ file_exists(public_path('js/form-wizard.js')) ? '?v='.filemtime(public_path('js/form-wizard.js')) : '' }}"></script>
    @if ($appLayout->on('loading_bar'))
        <div id="page-loading-bar" aria-hidden="true"></div>
        <script>
            (function () {
                var bar = document.getElementById('page-loading-bar');
                function start() { bar.classList.add('is-loading'); }
                document.addEventListener('click', function (e) {
                    var link = e.target.closest('a[href]');
                    if (!link || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || link.target === '_blank' || link.hasAttribute('download')) return;
                    var href = link.getAttribute('href');
                    if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0 || link.origin !== location.origin) return;
                    start();
                });
                document.addEventListener('submit', function (e) { if (!e.defaultPrevented && e.target.target !== '_blank') start(); });
                window.addEventListener('pageshow', function () { bar.classList.remove('is-loading'); });
            })();
        </script>
    @endif
    @if ($appLayout->on('back_to_top'))
        <button type="button" id="back-to-top" aria-label="{{ __('body.back_to_top') }}" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })"
                class="h-10 w-10 rounded-full bg-card border border-line shadow-lg text-body hover:text-brand flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
        </button>
        <script>
            (function () {
                var button = document.getElementById('back-to-top');
                window.addEventListener('scroll', function () { button.classList.toggle('is-visible', window.scrollY > 600); }, { passive: true });
            })();
        </script>
    @endif
    @stack('scripts')
</body>
</html>
