<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <x-common.brand-head :title="$title ?? 'Super Admin'" />
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
                success: '#16A34A', warning: '#F59E0B', danger: '#DC2626',
            },
        }}};
    </script>
    <style>body{font-family:Inter,sans-serif}.font-display{font-family:Inter,sans-serif}</style>
</head>
<body class="bg-surface text-body antialiased">
    <div class="min-h-screen lg:flex">
        <div id="sidebar-backdrop" onclick="toggleSidebar(false)" class="hidden fixed inset-0 bg-black/30 z-30 lg:hidden"></div>
        <aside id="dashboard-sidebar" class="fixed inset-y-0 left-0 z-40 w-[min(18rem,100%)] max-w-xs bg-ink text-white -translate-x-full lg:translate-x-0 lg:static lg:flex lg:flex-col lg:w-64 lg:max-w-none lg:shrink-0 transition-transform duration-200 flex flex-col overflow-hidden">
            <div class="h-16 flex items-center justify-between gap-2 px-5 border-b border-white/10 shrink-0">
                <x-common.logo dark />
                <button type="button" onclick="toggleSidebar(false)" class="lg:hidden p-2 -mr-2 text-white/70 hover:text-white" aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            @php
                $superAdminNav = [
                    ['label' => __('body.overview'), 'route' => 'superadmin.dashboard', 'active' => 'superadmin.dashboard'],
                    ['label' => __('body.businesses'), 'route' => 'superadmin.businesses.index', 'active' => 'superadmin.businesses.*'],
                    ['label' => __('body.modules'), 'route' => 'superadmin.modules.index', 'active' => 'superadmin.modules.*'],
                    ['label' => __('body.platform_settings'), 'route' => 'superadmin.settings.index', 'active' => 'superadmin.settings.*'],
                ];
            @endphp
            <nav class="flex-1 min-h-0 px-3 py-4 space-y-1 overflow-y-auto">
                @foreach ($superAdminNav as $item)
                    <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ request()->routeIs($item['active']) ? 'bg-white/10' : 'hover:bg-white/5' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="p-4 border-t border-white/10 space-y-3 shrink-0">
                <a href="{{ route('dashboard') }}" class="block text-sm font-medium text-slate-400 hover:text-white">{{ __('body.back_to_app') }}</a>
                <form action="{{ route('staff.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-slate-400 hover:text-white">{{ __('body.log_out') }}</button>
                </form>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <header class="h-16 bg-white border-b border-line flex items-center justify-between gap-3 px-4 sm:px-6">
                <div class="flex items-center gap-2 min-w-0">
                    <button type="button" onclick="toggleSidebar(true)" class="lg:hidden p-2 -ml-2 text-ink rounded-lg hover:bg-surface" aria-label="Open menu">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <p class="font-display font-bold text-ink truncate">{{ $title ?? 'Super Admin' }}</p>
                </div>
                <span class="text-sm text-muted hidden sm:inline shrink-0">{{ __('body.platform_owner') }}</span>
            </header>
            <main class="p-4 sm:p-6 lg:p-8">
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
                return;
            }
            var currentlyOpen = !sidebar.classList.contains('-translate-x-full');
            var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !currentlyOpen;
            sidebar.classList.toggle('-translate-x-full', !shouldOpen);
            backdrop.classList.toggle('hidden', !shouldOpen);
            document.body.classList.toggle('nav-locked', shouldOpen);
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggleSidebar(false);
        });
        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 1024px)').matches) toggleSidebar(false);
        });
    </script>

    <x-common.toast />
    <x-common.confirm-modal />
</body>
</html>
