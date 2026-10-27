@php
    $customer = auth('customer')->user();

    $items = [
        [
            'label' => __('body.my_account'),
            'route' => 'profile',
            'match' => 'profile',
            'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        ],
        [
            'label' => __('body.my_bookings'),
            'route' => 'account.bookings.index',
            'match' => 'account.bookings.*',
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
    ];

    $explore = [
        [
            'label' => __('body.book_a_car'),
            'route' => 'booking.search',
            'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
        ],
        [
            'label' => __('body.browse_vehicles'),
            'route' => 'vehicles.index',
            'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3',
        ],
    ];

    $profileActive = request()->routeIs('account.profile');
@endphp

<aside id="account-sidebar"
       class="hidden lg:flex lg:flex-col fixed top-16 sm:top-20 bottom-0 left-0 z-30 w-64 bg-white border-r border-line overflow-hidden"
       aria-label="{{ __('body.my_account') }}">

    @if ($customer)
        <div class="px-5 py-4 border-b border-line shrink-0">
            <p class="text-sm font-semibold text-ink truncate">{{ $customer->name }}</p>
            @if ($customer->email)
                <p class="text-xs text-muted truncate">{{ $customer->email }}</p>
            @endif
        </div>
    @endif

    <nav id="account-sidebar-nav" class="flex-1 min-h-0 overflow-y-auto px-3 py-4 space-y-6">
        <div class="space-y-0.5">
            @foreach ($items as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $active ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0 {{ $active ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <div>
            <p class="px-3 text-[11px] font-semibold text-muted tracking-wider uppercase mb-2">{{ __('body.book_now') }}</p>
            <div class="space-y-0.5">
                @foreach ($explore as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-body hover:bg-surface">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="pt-2 border-t border-line space-y-0.5">
            <a href="{{ route('account.profile') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors {{ $profileActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] shrink-0 {{ $profileActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                {{ __('body.profile') }}
            </a>
        </div>
    </nav>

    <div class="p-4 border-t border-line shrink-0 space-y-2 bg-white">
        <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            {{ __('body.back_to_website') }}
        </a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface w-full text-left">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" transform="rotate(180 12 12)"/></svg>
                {{ __('body.log_out') }}
            </button>
        </form>
    </div>
</aside>
