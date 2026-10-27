<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <x-common.brand-head :title="$title ?? 'Dashboard'" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..700;1,14..32,300..700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                fontFamily: {
                    display: ['Inter', 'sans-serif'],
                    sans: ['Inter', 'sans-serif'],
                },
                colors: {
                    brand: { DEFAULT: 'var(--brand)', dark: 'var(--brand-dark)', light: 'var(--brand-light)' },
                    ink: '#111827', body: '#1F2937', muted: '#6B7280',
                    surface: '#F8FAFC', line: '#E5E7EB',
                    success: '#16A34A', warning: '#F59E0B', danger: '#DC2626',
                },
            }},
        };
    </script>
    <style>
        body { font-family: Inter, sans-serif; }
        .font-display { font-family: Inter, sans-serif; }
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
<body class="bg-surface text-body antialiased">

    <div class="min-h-screen lg:flex">

        <x-dashboard.sidebar />

        <div class="flex-1 min-w-0 lg:pl-64">
            <x-dashboard.impersonation-banner />
            <x-dashboard.header :title="$title ?? 'Dashboard'" />

            <main class="p-4 sm:p-5 lg:p-8">
                {{ $slot }}
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
    <script src="{{ asset('js/datetime-split.js') }}"></script>
    <script src="{{ asset('js/searchable-select.js') }}{{ file_exists(public_path('js/searchable-select.js')) ? '?v='.filemtime(public_path('js/searchable-select.js')) : '' }}"></script>
    <script src="{{ asset('js/form-wizard.js') }}{{ file_exists(public_path('js/form-wizard.js')) ? '?v='.filemtime(public_path('js/form-wizard.js')) : '' }}"></script>
    @stack('scripts')
</body>
</html>
