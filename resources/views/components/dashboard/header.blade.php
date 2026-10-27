@props(['title' => 'Dashboard'])

@php
    $authUser = auth('staff')->user();
    $initials = collect(explode(' ', $authUser->name))
        ->filter()
        ->map(fn ($part) => strtoupper($part[0]))
        ->take(2)
        ->implode('');
    $headerNotifications = $headerNotifications ?? collect();
    $headerUnreadCount = $headerUnreadCount ?? 0;
@endphp

<header class="h-16 bg-white border-b border-line flex items-center justify-between gap-3 px-4 sm:px-5 lg:px-8 sticky top-0 z-20">

    <div class="flex items-center gap-3 min-w-0">
        <button type="button" onclick="toggleSidebar(true)" class="lg:hidden text-ink p-2 -ml-2 rounded-lg hover:bg-surface" aria-label="Open menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
        </button>
    </div>

    <div class="flex items-center gap-2 sm:gap-4 shrink-0">
        <div class="relative" data-notification-menu>
            <button type="button"
                    class="relative p-2 text-muted hover:text-ink rounded-lg hover:bg-surface"
                    aria-label="{{ __('body.notifications') }}"
                    aria-expanded="false"
                    aria-haspopup="true"
                    data-notification-toggle>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                @if ($headerUnreadCount > 0)
                    <span class="absolute top-1 right-1 min-w-[1rem] h-4 px-1 rounded-full bg-danger text-white text-[10px] font-bold leading-4 text-center">
                        {{ $headerUnreadCount > 9 ? '9+' : $headerUnreadCount }}
                    </span>
                @endif
            </button>

            <div hidden
                 data-notification-panel
                 class="absolute right-0 mt-2 w-[min(22rem,calc(100vw-2rem))] bg-white border border-line rounded-xl shadow-lg z-50 overflow-hidden">
                <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-ink">{{ __('body.notifications') }}</p>
                    @if ($headerUnreadCount > 0)
                        <form action="{{ route('notifications.read-all') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-brand hover:text-brand-dark">{{ __('body.mark_all_as_read') }}</button>
                        </form>
                    @endif
                </div>

                <div class="max-h-80 overflow-y-auto divide-y divide-line">
                    @forelse ($headerNotifications as $notification)
                        <a href="{{ route('notifications.show', $notification) }}"
                           class="block px-4 py-3 hover:bg-surface transition-colors {{ $notification->isUnread() ? 'bg-brand-light/40' : '' }}">
                            <div class="flex items-start gap-2">
                                @if ($notification->isUnread())
                                    <span class="mt-1.5 h-2 w-2 rounded-full bg-brand shrink-0"></span>
                                @else
                                    <span class="mt-1.5 h-2 w-2 rounded-full bg-transparent shrink-0"></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-ink truncate">{{ $notification->title }}</p>
                                    @if ($notification->body)
                                        <p class="text-xs text-muted mt-0.5 line-clamp-2">{{ $notification->body }}</p>
                                    @endif
                                    <p class="text-[11px] text-muted mt-1">{{ $notification->created_at?->diffForHumans() }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-muted">{{ __('body.no_notifications_yet') }}</div>
                    @endforelse
                </div>

                <div class="px-4 py-3 border-t border-line bg-surface">
                    <a href="{{ route('notifications.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">
                        {{ __('body.view_all_notifications') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="h-8 w-px bg-line hidden sm:block"></div>

        <div class="relative" data-user-menu>
            <button type="button"
                    class="flex items-center gap-3 min-w-0 rounded-lg p-1 -m-1 hover:bg-surface transition-colors"
                    aria-label="{{ __('body.profile') }}"
                    aria-expanded="false"
                    aria-haspopup="true"
                    data-user-toggle>
                <div class="h-9 w-9 rounded-full bg-brand-light text-brand-dark flex items-center justify-center font-semibold text-sm shrink-0">
                    {{ $initials }}
                </div>
                <div class="hidden sm:block leading-tight min-w-0 text-left">
                    <p class="text-sm font-semibold text-ink truncate max-w-[10rem] lg:max-w-[14rem]">{{ $authUser->name }}</p>
                    <p class="text-xs text-muted truncate">{{ $authUser->getRoleNames()->first() ?? __('body.staff') }}</p>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="hidden sm:block h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div hidden
                 data-user-panel
                 class="absolute right-0 mt-2 w-56 bg-white border border-line rounded-xl shadow-lg z-50 overflow-hidden py-1">
                <a href="{{ route('staff.profile') }}"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ __('body.profile') }}
                </a>
                <form action="{{ route('staff.logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-ink hover:bg-surface transition-colors w-full text-left">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        {{ __('body.log_out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

@once
@push('scripts')
<script>
    (function () {
        var menus = [
            { root: '[data-notification-menu]', toggle: '[data-notification-toggle]', panel: '[data-notification-panel]' },
            { root: '[data-user-menu]', toggle: '[data-user-toggle]', panel: '[data-user-panel]' },
        ].map(function (cfg) {
            var root = document.querySelector(cfg.root);
            if (!root) return null;
            var toggle = root.querySelector(cfg.toggle);
            var panel = root.querySelector(cfg.panel);
            if (!toggle || !panel) return null;
            return { root: root, toggle: toggle, panel: panel };
        }).filter(Boolean);

        function closeAll(except) {
            menus.forEach(function (menu) {
                if (menu === except) return;
                menu.panel.hidden = true;
                menu.toggle.setAttribute('aria-expanded', 'false');
            });
        }

        menus.forEach(function (menu) {
            menu.toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = menu.panel.hidden;
                closeAll(menu);
                menu.panel.hidden = !open;
                menu.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        document.addEventListener('click', function (e) {
            if (menus.some(function (menu) { return menu.root.contains(e.target); })) return;
            closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });
    })();
</script>
@endpush
@endonce
