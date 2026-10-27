@php
    $actions = collect([
        [
            'label' => __('body.add_booking'),
            'route' => 'bookings.create',
            'permission' => 'create bookings.booking',
            'color' => 'teal',
            'description' => __('body.quick_action_add_booking'),
            'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
        ],
        [
            'label' => __('body.bookings'),
            'route' => 'bookings.index',
            'permission' => 'list bookings.booking',
            'color' => 'indigo',
            'description' => __('body.quick_action_bookings'),
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        ],
        [
            'label' => __('body.rentals'),
            'route' => 'rentals.index',
            'permission' => 'list operations.rental',
            'color' => 'blue',
            'description' => __('body.quick_action_rentals'),
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        ],
        [
            'label' => __('body.add_vehicle'),
            'route' => 'fleet.vehicles.create',
            'permission' => 'create fleet.vehicle',
            'color' => 'green',
            'description' => __('body.quick_action_add_vehicle'),
            'icon' => 'M5 17h14M5 17a2 2 0 104 0M5 17a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0M5 17l1.5-5.5A2 2 0 018.4 10h7.2a2 2 0 011.9 1.5L19 17M7 10V7a2 2 0 012-2h6a2 2 0 012 2v3',
        ],
        [
            'label' => __('body.invoices'),
            'route' => 'finance.invoices.index',
            'permission' => 'list finance.invoice',
            'color' => 'purple',
            'description' => __('body.quick_action_invoices'),
            'icon' => 'M9 14l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'label' => __('body.payments'),
            'route' => 'finance.payments.index',
            'permission' => 'list finance.payment',
            'color' => 'pink',
            'description' => __('body.quick_action_payments'),
            'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h16a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        ],
        [
            'label' => __('body.customers'),
            'route' => 'customers.index',
            'permission' => 'list customers.customer',
            'color' => 'yellow',
            'description' => __('body.quick_action_customers'),
            'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z',
        ],
        [
            'label' => __('body.maintenance'),
            'route' => 'fleet.maintenance.index',
            'permission' => 'list fleet.vehicle_maintenance',
            'color' => 'gray',
            'description' => __('body.quick_action_maintenance'),
            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
        ],
    ])->filter(fn (array $action) => \App\Helpers\AuthHelper::can($action['permission']));

    $colors = [
        'blue' => 'bg-blue-50 hover:bg-blue-100 text-blue-600 border-blue-200',
        'green' => 'bg-green-50 hover:bg-green-100 text-green-700 border-green-200',
        'yellow' => 'bg-amber-50 hover:bg-amber-100 text-amber-700 border-amber-200',
        'purple' => 'bg-violet-50 hover:bg-violet-100 text-violet-700 border-violet-200',
        'indigo' => 'bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border-indigo-200',
        'pink' => 'bg-pink-50 hover:bg-pink-100 text-pink-600 border-pink-200',
        'gray' => 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200',
        'teal' => 'bg-teal-50 hover:bg-teal-100 text-teal-700 border-teal-200',
    ];
@endphp

<div class="bg-white border border-line rounded-xl p-4">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-1.5 h-6 rounded-full bg-brand"></div>
        <h2 class="text-base font-semibold text-ink">{{ __('body.quick_actions') }}</h2>
    </div>

    @if ($actions->isEmpty())
        <p class="text-sm text-muted py-6 text-center">{{ __('body.no_quick_actions') }}</p>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2">
            @foreach ($actions as $action)
                <a href="{{ route($action['route']) }}"
                   title="{{ $action['description'] }}"
                   class="p-2 rounded-md border transition-all duration-200 hover:scale-105 hover:shadow-sm {{ $colors[$action['color']] ?? $colors['blue'] }}">
                    <div class="flex flex-col items-center gap-1.5">
                        <div class="p-1 rounded-md bg-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-center leading-tight">{{ $action['label'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
