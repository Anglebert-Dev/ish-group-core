@php
    $actions = [
        [
            'label' => __('body.book_a_car'),
            'route' => 'booking.search',
            'color' => 'teal',
            'description' => __('body.book_a_car_hint'),
            'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
        ],
        [
            'label' => __('body.my_bookings'),
            'route' => 'account.bookings.index',
            'color' => 'indigo',
            'description' => __('body.my_bookings_hint'),
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        [
            'label' => __('body.profile'),
            'route' => 'account.profile',
            'color' => 'blue',
            'description' => __('body.my_details_hint'),
            'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        ],
        [
            'label' => __('body.browse_vehicles'),
            'route' => 'vehicles.index',
            'color' => 'green',
            'description' => __('body.browse_vehicles_hint'),
            'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3',
        ],
    ];

    $colors = [
        'blue' => 'bg-blue-50 hover:bg-blue-100 text-blue-600 border-blue-200',
        'green' => 'bg-green-50 hover:bg-green-100 text-green-700 border-green-200',
        'indigo' => 'bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border-indigo-200',
        'teal' => 'bg-teal-50 hover:bg-teal-100 text-teal-700 border-teal-200',
    ];
@endphp

<div class="bg-white border border-line rounded-xl p-4">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-1.5 h-6 rounded-full bg-brand"></div>
        <h2 class="text-base font-semibold text-ink">{{ __('body.quick_actions') }}</h2>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        @foreach ($actions as $action)
            <a href="{{ route($action['route']) }}"
               title="{{ $action['description'] }}"
               class="p-3 rounded-md border transition-all duration-200 hover:scale-105 hover:shadow-sm {{ $colors[$action['color']] ?? $colors['blue'] }}">
                <div class="flex flex-col items-center gap-1.5">
                    <div class="p-1.5 rounded-md bg-white shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-center leading-tight">{{ $action['label'] }}</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
