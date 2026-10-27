{{-- $menuGroups comes from App\Support\Menu\AdminMenu (config/menu.php + module hooks). --}}

<div id="sidebar-backdrop" onclick="toggleSidebar()" class="hidden fixed inset-0 bg-black/30 z-30 lg:hidden"></div>

<aside id="dashboard-sidebar"
       class="fixed inset-y-0 left-0 z-40 w-[min(18rem,100%)] max-w-xs lg:w-64 lg:max-w-none bg-white border-r border-line -translate-x-full lg:translate-x-0 transition-transform duration-200 flex flex-col overflow-hidden"
       aria-hidden="true">

    <div class="h-16 flex items-center justify-between gap-2 px-5 border-b border-line shrink-0">
        <x-common.logo compact :href="route('dashboard')" />
        <button type="button" onclick="toggleSidebar(false)" class="lg:hidden p-2 -mr-2 text-muted hover:text-ink" aria-label="Close menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav id="dashboard-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-6">
        @foreach ($menuGroups as $groupName => $items)
            <div>
                @if ($groupName !== '')
                    <p class="px-3 text-[11px] font-semibold text-muted tracking-wider uppercase mb-2">{{ $groupName }}</p>
                @endif
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item['active']); @endphp
                        <a href="{{ route($item['route']) }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                  {{ $active ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="p-4 border-t border-line shrink-0 space-y-2 bg-white">
        <x-common.timezone-note compact class="px-3" />
        <form action="{{ route('staff.logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface w-full text-left">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" transform="rotate(180 12 12)"/></svg>
                {{ __('body.log_out') }}
            </button>
        </form>
    </div>
</aside>
