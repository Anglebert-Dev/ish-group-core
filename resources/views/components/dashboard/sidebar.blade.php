@php
    $groups = [
        null => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ],
        'Customers' => [
            ['label' => 'Customers', 'route' => 'customers.index', 'permission' => 'list customers.customer', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z'],
            ['label' => 'Corporate Customers', 'route' => 'customers.corporate.index', 'permission' => 'list customers.corporate_customer', 'icon' => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1'],
        ],
        'Operations' => [
            ['label' => 'Bookings', 'route' => 'bookings.index', 'permission' => 'list bookings.booking', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['label' => 'Rentals', 'route' => 'rentals.index', 'permission' => 'list operations.rental', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['label' => 'Chauffeur Jobs', 'route' => 'chauffeur.index', 'permission' => 'list drivers.chauffeur_job', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['label' => 'Extras', 'route' => 'extras.index', 'permission' => 'list bookings.booking_extra', 'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6'],
        ],
        'Fleet' => [
            ['label' => 'Vehicles', 'route' => 'fleet.vehicles.index', 'permission' => 'list fleet.vehicle', 'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3'],
            ['label' => 'Vehicle Owners', 'route' => 'fleet.owners.index', 'permission' => 'list fleet.vehicle_owner', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => 'Categories', 'route' => 'fleet.categories.index', 'permission' => 'list fleet.vehicle_category', 'icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
            ['label' => 'Maintenance', 'route' => 'fleet.maintenance.index', 'permission' => 'list fleet.vehicle_maintenance', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
            ['label' => 'Fuel Logs', 'route' => 'fleet.fuel_logs.index', 'permission' => 'list fleet.fuel_log', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['label' => 'Drivers', 'route' => 'drivers.index', 'permission' => 'list drivers.driver', 'icon' => 'M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z'],
        ],
        'Finance' => [
            ['label' => 'Finance Home', 'route' => 'finance.index', 'permission' => null, 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['label' => 'Invoices', 'route' => 'finance.invoices.index', 'permission' => 'list finance.invoice', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['label' => 'Payments', 'route' => 'finance.payments.index', 'permission' => 'list finance.payment', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['label' => 'Expenses', 'route' => 'finance.expenses.index', 'permission' => 'list finance.expense', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m9-8a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Suppliers', 'route' => 'finance.suppliers.index', 'permission' => 'list finance.supplier', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['label' => 'Chart of Accounts', 'route' => 'finance.chart-of-accounts.index', 'permission' => 'list finance.chart_of_account', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
            ['label' => 'Ledger', 'route' => 'finance.ledger.index', 'permission' => 'list finance.general_ledger', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'Statements', 'route' => 'finance.statements.index', 'permission' => 'list finance.financial_statement', 'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ],
        'Management' => [
            ['label' => 'Users', 'route' => 'users.index', 'permission' => 'list users.user', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['label' => 'Roles', 'route' => 'roles.index', 'permission' => 'list users.role', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['label' => 'Stations', 'route' => 'stations.index', 'permission' => 'list stations.station', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
        ],
    ];

    $visibleGroups = collect($groups)->map(function ($items) {
        return collect($items)->filter(fn ($item) => is_null($item['permission']) || \App\Helpers\AuthHelper::can($item['permission']));
    })->filter(fn ($items) => $items->isNotEmpty());

    $canViewReports = \App\Helpers\AuthHelper::can('list reports.report');
    $canViewSettings = \App\Helpers\AuthHelper::can('list settings.setting');
    $canViewActivityLogs = \App\Helpers\AuthHelper::can('list activity.activity_log');
    $canViewNotifications = \App\Helpers\AuthHelper::can('list notifications.notification');
@endphp

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
        @foreach ($visibleGroups as $groupName => $items)
            <div>
                @if ($groupName)
                    <p class="px-3 text-[11px] font-semibold text-muted tracking-wider uppercase mb-2">{{ $groupName }}</p>
                @endif
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        @php $active = Route::has($item['route']) && request()->routeIs($item['route'] . '*'); @endphp
                        <a href="{{ Route::has($item['route']) ? route($item['route']) : '#' }}"
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

        <div class="pt-2 border-t border-line space-y-0.5">
                @if ($canViewNotifications)
                    @php $notificationsActive = Route::has('notifications.index') && request()->routeIs('notifications.*'); @endphp
                    <a href="{{ Route::has('notifications.index') ? route('notifications.index') : '#' }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $notificationsActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] {{ $notificationsActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        {{ __('body.notifications') }}
                    </a>
                @endif
                @if ($canViewReports)
                    @php $reportsActive = Route::has('reports.index') && request()->routeIs('reports.*'); @endphp
                    <a href="{{ Route::has('reports.index') ? route('reports.index') : '#' }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $reportsActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] {{ $reportsActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Reports
                    </a>
                @endif
                @if ($canViewSettings)
                    @php $settingsActive = Route::has('settings.index') && request()->routeIs('settings.*'); @endphp
                    <a href="{{ Route::has('settings.index') ? route('settings.index') : '#' }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $settingsActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] {{ $settingsActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Settings
                    </a>
                @endif
                @if ($canViewActivityLogs)
                    @php $activityLogsActive = Route::has('activity-logs.index') && request()->routeIs('activity-logs.*'); @endphp
                    <a href="{{ Route::has('activity-logs.index') ? route('activity-logs.index') : '#' }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $activityLogsActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] {{ $activityLogsActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        Audit Logs
                    </a>
                @endif
                @php $profileActive = Route::has('staff.profile') && request()->routeIs('staff.profile*'); @endphp
                <a href="{{ route('staff.profile') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium {{ $profileActive ? 'bg-brand-light text-brand-dark' : 'text-body hover:bg-surface' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px] {{ $profileActive ? 'text-brand' : 'text-muted' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ __('body.profile') }}
                </a>
            </div>
    </nav>

    <div class="p-4 border-t border-line shrink-0 space-y-2 bg-white">
        <x-common.timezone-note compact class="px-3" />
        <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            {{ __('body.back_to_website') }}
        </a>
        <form action="{{ route('staff.logout') }}" method="POST">
            @csrf
            <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-muted hover:bg-surface w-full text-left">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" transform="rotate(180 12 12)"/></svg>
                {{ __('body.log_out') }}
            </button>
        </form>
    </div>
</aside>
