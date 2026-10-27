@php
    $overlay = request()->routeIs('site.home', 'home');
    $header = $site->get('header');
    $showAccount = (bool) $header['show_account'];
    $canRegister = $showAccount && (bool) $site->get('general.allow_registration');
    $customer = auth('customer')->user();
    $initials = $customer
        ? collect(explode(' ', $customer->name))->filter()->map(fn ($part) => strtoupper($part[0]))->take(2)->implode('')
        : '';
    $navItems = [
        ['label' => __('body.home'), 'href' => route('site.home'), 'active' => request()->routeIs('site.home', 'home'), 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        $header['show_services'] ? ['label' => __('body.services'), 'href' => route('services'), 'active' => request()->routeIs('services'), 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'] : null,
        $header['show_about'] ? ['label' => __('body.about_us'), 'href' => route('about'), 'active' => request()->routeIs('about'), 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'] : null,
        $header['show_contact'] ? ['label' => __('body.contact'), 'href' => route('contact'), 'active' => request()->routeIs('contact'), 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'] : null,
    ];
    $navItems = array_values(array_filter($navItems));
@endphp

<header
    id="site-navbar"
    @if ($overlay)
        data-landing-nav
        data-solid="false"
    @endif
    class="{{ $overlay
        ? 'landing-nav fixed top-0 inset-x-0 z-50 transition-[background-color,border-color,box-shadow,backdrop-filter] duration-300'
        : 'sticky top-0 z-50 border-b border-line bg-white' }}"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 grid grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-4">

        <div class="relative min-w-0 max-w-[10rem] sm:max-w-[14rem] lg:max-w-none">
            @if ($overlay)
                <div data-nav-logo="dark" class="landing-nav-logo-dark">
                    <x-common.logo dark :href="route('site.home')" />
                </div>
                <div data-nav-logo="light" class="landing-nav-logo-light hidden">
                    <x-common.logo :href="route('site.home')" />
                </div>
            @else
                <x-common.logo :href="route('site.home')" />
            @endif
        </div>

        <nav class="hidden lg:flex items-center gap-8">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}"
                   class="landing-nav-link text-[15px] font-medium transition-colors {{ $overlay
                        ? ($item['active'] ? 'is-active' : '')
                        : ($item['active'] ? 'text-brand' : 'text-body hover:text-brand') }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center justify-end gap-2 sm:gap-3 shrink-0">
            @if ($showAccount)
            @auth('customer')
                <div class="relative hidden sm:block" data-user-menu>
                    <button type="button"
                            class="landing-nav-muted inline-flex items-center gap-2 text-[15px] font-medium transition-colors px-2.5 py-1.5 rounded-lg {{ $overlay ? 'hover:bg-white/10' : 'text-body hover:text-brand hover:bg-surface' }}"
                            aria-label="{{ __('body.my_account') }}"
                            aria-expanded="false"
                            aria-haspopup="true"
                            data-user-toggle>
                        <span class="h-8 w-8 rounded-full bg-brand-light text-brand-dark flex items-center justify-center font-semibold text-xs shrink-0">{{ $initials }}</span>
                        <span class="max-w-[8rem] truncate">{{ Str::of($customer->name)->before(' ') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div hidden
                         data-user-panel
                         class="absolute right-0 mt-2 w-56 bg-white border border-line rounded-xl shadow-lg z-50 overflow-hidden py-1 text-body">
                        <a href="{{ route('profile') }}"
                           class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            {{ __('body.my_account') }}
                        </a>
                        <a href="{{ route('account.profile') }}"
                           class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ __('body.profile') }}
                        </a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface transition-colors w-full text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                {{ __('body.log_out') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}"
                   class="landing-nav-login hidden sm:inline-flex items-center text-[15px] font-medium px-4 py-2 rounded-lg transition-colors {{ $overlay ? '' : 'text-body hover:text-brand' }}">
                    {{ __('body.log_in') }}
                </a>
            @endauth

            @if ($canRegister && auth('customer')->guest())
                <x-common.button href="{{ route('register') }}" class="!px-3.5 !py-2 !text-sm sm:!px-5 sm:!py-2.5 sm:!text-[15px]">
                    {{ __('body.sign_up') }}
                </x-common.button>
            @endif
            @endif

            <button type="button" onclick="toggleMobileNav()"
                    class="landing-nav-toggle lg:hidden p-2 rounded-lg {{ $overlay ? '' : 'text-ink hover:bg-surface' }}"
                    aria-label="{{ __('body.menu') }}"
                    aria-controls="mobile-nav"
                    aria-expanded="false"
                    data-mobile-nav-toggle>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" data-nav-icon-open fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" data-nav-icon-close fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

</header>

{{-- Full-viewport mobile menu --}}
<div id="mobile-nav" class="lg:hidden fixed inset-0 z-[80] hidden" aria-hidden="true" data-mobile-nav-root>
    <div
        class="fixed inset-0 flex flex-col bg-white/80 backdrop-blur-xl border-r border-white/40 shadow-xl translate-x-full transition-transform duration-300 ease-out"
        style="top:0;right:0;bottom:0;left:0;width:100%;height:100%;min-height:100vh;min-height:100dvh;min-height:-webkit-fill-available;"
        data-mobile-nav-panel
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('body.menu') }}"
    >
        <div class="flex items-center justify-between gap-3 px-4 min-h-16 border-b border-line/70 shrink-0 bg-white/50" style="padding-top: env(safe-area-inset-top);">
            <p class="font-semibold text-ink">{{ __('body.menu') }}</p>
            <button type="button" onclick="toggleMobileNav(false)" class="p-2 rounded-lg text-ink hover:bg-surface" aria-label="{{ __('body.close') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-3 py-4 space-y-5">
            <div>
                <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('body.explore') }}</p>
                <nav class="space-y-1">
                    @foreach ($navItems as $item)
                        <a
                            href="{{ $item['href'] }}"
                            class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium transition-colors {{ $item['active'] ? 'bg-brand-light/90 text-brand' : 'text-ink hover:bg-white/70' }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            @if ($showAccount)
            <div class="border-t border-line/70 pt-4">
                <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wide text-muted">{{ __('body.account') }}</p>
                <nav class="space-y-1">
                    @auth('customer')
                        <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-ink hover:bg-white/70 transition-colors {{ request()->routeIs('profile') ? 'bg-brand-light/90 text-brand' : '' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="min-w-0 truncate">{{ $customer->name }}</span>
                        </a>
                        <a href="{{ route('account.profile') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-ink hover:bg-white/70 transition-colors {{ request()->routeIs('account.profile') ? 'bg-brand-light/90 text-brand' : '' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>{{ __('body.profile') }}</span>
                        </a>
                        <form action="{{ route('logout') }}" method="POST" class="pt-1">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-danger hover:bg-red-50/80 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                <span>{{ __('body.log_out') }}</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-ink hover:bg-white/70 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            <span>{{ __('body.log_in') }}</span>
                        </a>
                        @if ($canRegister)
                        <a href="{{ route('register') }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-ink hover:bg-white/70 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            <span>{{ __('body.sign_up') }}</span>
                        </a>
                        @endif
                    @endauth
                </nav>
            </div>
            @endif
        </div>

        <div class="shrink-0 border-t border-line/70 p-4 bg-white/50" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
            <x-common.button href="{{ route('contact') }}" class="w-full justify-center">
                {{ __('body.contact_us') }}
            </x-common.button>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function toggleMobileNav(forceOpen) {
                var root = document.getElementById('mobile-nav');
                var toggle = document.querySelector('[data-mobile-nav-toggle]');
                var panel = root?.querySelector('[data-mobile-nav-panel]');
                var iconOpen = toggle?.querySelector('[data-nav-icon-open]');
                var iconClose = toggle?.querySelector('[data-nav-icon-close]');
                if (!root || !panel) return;

                if (root.parentElement !== document.body) {
                    document.body.appendChild(root);
                }

                var currentlyOpen = root.getAttribute('data-open') === 'true';
                var shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !currentlyOpen;

                function syncViewportHeight() {
                    var h = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                    root.style.top = '0';
                    root.style.left = '0';
                    root.style.right = '0';
                    root.style.bottom = '0';
                    root.style.width = '100%';
                    root.style.height = h + 'px';
                    panel.style.height = h + 'px';
                    panel.style.minHeight = h + 'px';
                }

                if (shouldOpen) {
                    syncViewportHeight();
                    root.classList.remove('hidden');
                    root.setAttribute('aria-hidden', 'false');
                    requestAnimationFrame(function () {
                        root.setAttribute('data-open', 'true');
                        panel.classList.remove('translate-x-full');
                    });
                } else {
                    root.setAttribute('data-open', 'false');
                    panel.classList.add('translate-x-full');
                    setTimeout(function () {
                        if (root.getAttribute('data-open') !== 'true') {
                            root.classList.add('hidden');
                            root.setAttribute('aria-hidden', 'true');
                            root.style.height = '';
                            panel.style.height = '';
                            panel.style.minHeight = '';
                        }
                    }, 280);
                }

                document.body.classList.toggle('nav-locked', shouldOpen);
                if (toggle) toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                if (iconOpen) iconOpen.classList.toggle('hidden', shouldOpen);
                if (iconClose) iconClose.classList.toggle('hidden', !shouldOpen);
            }

            document.addEventListener('DOMContentLoaded', function () {
                var root = document.getElementById('mobile-nav');
                if (root) {
                    if (root.parentElement !== document.body) {
                        document.body.appendChild(root);
                    }

                    root.querySelectorAll('a').forEach(function (link) {
                        link.addEventListener('click', function () { toggleMobileNav(false); });
                    });
                }

                var menu = document.querySelector('[data-user-menu]');
                var toggle = menu?.querySelector('[data-user-toggle]');
                var panel = menu?.querySelector('[data-user-panel]');
                if (menu && toggle && panel) {
                    function closeUserMenu() {
                        panel.hidden = true;
                        toggle.setAttribute('aria-expanded', 'false');
                    }

                    toggle.addEventListener('click', function (e) {
                        e.stopPropagation();
                        var open = panel.hidden;
                        panel.hidden = !open;
                        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    });

                    document.addEventListener('click', function (e) {
                        if (!menu.contains(e.target)) closeUserMenu();
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') closeUserMenu();
                    });
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') toggleMobileNav(false);
            });

            window.addEventListener('resize', function () {
                var root = document.getElementById('mobile-nav');
                var panel = root?.querySelector('[data-mobile-nav-panel]');
                if (window.matchMedia('(min-width: 1024px)').matches) {
                    toggleMobileNav(false);
                    return;
                }
                if (root && panel && root.getAttribute('data-open') === 'true') {
                    var h = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                    root.style.height = h + 'px';
                    panel.style.height = h + 'px';
                    panel.style.minHeight = h + 'px';
                }
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', function () {
                    var root = document.getElementById('mobile-nav');
                    var panel = root?.querySelector('[data-mobile-nav-panel]');
                    if (!root || !panel || root.getAttribute('data-open') !== 'true') return;
                    var h = window.visualViewport.height;
                    root.style.height = h + 'px';
                    panel.style.height = h + 'px';
                    panel.style.minHeight = h + 'px';
                });
            }
        </script>
    @endpush
@endonce

@if ($overlay)
    @once
        @push('scripts')
            <script>
                (function () {
                    var nav = document.querySelector('[data-landing-nav]');
                    if (!nav) return;

                    var logoDark = nav.querySelector('[data-nav-logo="dark"]');
                    var logoLight = nav.querySelector('[data-nav-logo="light"]');

                    function setSolid(solid) {
                        nav.setAttribute('data-solid', solid ? 'true' : 'false');
                        if (logoDark) logoDark.classList.toggle('hidden', solid);
                        if (logoLight) logoLight.classList.toggle('hidden', !solid);
                    }

                    function onScroll() {
                        setSolid(window.scrollY > 24);
                    }

                    window.addEventListener('scroll', onScroll, { passive: true });
                    onScroll();
                })();
            </script>
        @endpush
    @endonce
@endif
