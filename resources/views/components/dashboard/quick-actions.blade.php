@props(['actions'])

@php
    // $actions: core quick actions + every active module's
    // DataController::dashboard_quick_actions() items, already filtered by
    // permission in DashboardService.
    $actions = collect($actions);

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
