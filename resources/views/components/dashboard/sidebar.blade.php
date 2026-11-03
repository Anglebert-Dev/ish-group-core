{{-- $menuGroups comes from App\Support\Menu\AdminMenu (config/menu.php + module hooks).
     Colours come from the sidebar tokens, so a dark sidebar stays readable. --}}
@php
    // Dark mode: the dark sidebar colour decides; "system" can't be known here, so light's does.
    $brandingService = app(\App\Services\Branding\BrandingService::class);
    $sidebarTokens = $brandingService->effectiveMode($branding) === 'dark'
        ? app(\App\Services\Branding\ThemeService::class)->tokens($branding, $brandingService->previewing($branding), dark: true)
        : $brandingService->tokens($branding);
    $sidebarDark = \App\Libraries\ColorLibrary::isDark($sidebarTokens['sidebar']);
@endphp

<div id="sidebar-backdrop" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/30 z-30 lg:hidden"></div>

<aside id="dashboard-sidebar"
       class="fixed inset-y-0 left-0 z-40 w-[min(18rem,100%)] max-w-xs lg:w-[var(--sidebar-w)] lg:max-w-none bg-sidebar text-sidebar-ink border-r border-sidebar-ink/10 -translate-x-full lg:translate-x-0 transition-transform duration-200 flex flex-col overflow-hidden"
       aria-hidden="true">

    <div class="sidebar-head h-[var(--header-h)] flex items-center justify-between gap-2 px-5 border-b border-sidebar-ink/10 shrink-0">
        <x-common.logo compact :dark="$sidebarDark" :href="route('dashboard')" />
        <button type="button" onclick="toggleSidebar(false)" class="lg:hidden p-2 -mr-2 text-sidebar-ink/60 hover:text-sidebar-ink" aria-label="{{ __('body.close_menu') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav id="dashboard-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-6">
        @foreach ($menuGroups as $groupName => $items)
            <div>
                @if ($groupName !== '')
                    <p class="sidebar-label px-3 text-[11px] font-semibold text-sidebar-ink/60 tracking-wider uppercase mb-2">{{ $groupName }}</p>
                @endif
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item['active']); @endphp
                        <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                  {{ $active ? 'bg-brand-light text-brand-dark' : 'text-sidebar-ink hover:bg-sidebar-ink/5' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand' : 'text-sidebar-ink/60' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            <span class="sidebar-label truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="sidebar-foot p-4 border-t border-sidebar-ink/10 shrink-0 space-y-2 bg-sidebar">
        <x-common.timezone-note compact class="sidebar-note px-3 !text-sidebar-ink/60" />
        <form action="{{ route('staff.logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-sidebar-ink/70 hover:bg-sidebar-ink/5 w-full text-left">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" transform="rotate(180 12 12)"/></svg>
                <span class="sidebar-label">{{ __('body.log_out') }}</span>
            </button>
        </form>
        {{-- Desktop: shrink to icons and back; remembered in this browser. --}}
        <button type="button" id="sidebar-collapse" class="hidden lg:flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-sidebar-ink/70 hover:bg-sidebar-ink/5 w-full text-left"
                aria-label="{{ __('body.sidebar_collapse') }}" title="{{ __('body.sidebar_collapse') }}"
                onclick="var c = document.documentElement.classList.toggle('sidebar-collapsed'); try { localStorage.setItem('sidebar-collapsed', c ? '1' : '0'); } catch (e) {}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            <span class="sidebar-label">{{ __('body.sidebar_collapse') }}</span>
        </button>
    </div>
</aside>
