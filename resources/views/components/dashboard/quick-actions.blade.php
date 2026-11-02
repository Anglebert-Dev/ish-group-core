@props(['actions'])

@php
    // $actions: core quick actions + every active module's
    // DataController::dashboard_quick_actions() items, already filtered by
    // permission in DashboardService.
    $actions = collect($actions);

    $colors = [
        'blue' => 'bg-chart-1/10 hover:bg-chart-1/15 text-chart-1 border-chart-1/30',
        'green' => 'bg-success/10 hover:bg-success/15 text-success border-success/30',
        'yellow' => 'bg-warning/10 hover:bg-warning/15 text-ink border-warning/30',
        'purple' => 'bg-chart-2/10 hover:bg-chart-2/15 text-chart-2 border-chart-2/30',
        'indigo' => 'bg-chart-3/10 hover:bg-chart-3/15 text-chart-3 border-chart-3/30',
        'pink' => 'bg-chart-4/10 hover:bg-chart-4/15 text-chart-4 border-chart-4/30',
        'gray' => 'bg-surface hover:bg-line/60 text-body border-line',
        'teal' => 'bg-chart-5/10 hover:bg-chart-5/15 text-chart-5 border-chart-5/30',
    ];
@endphp

<div class="bg-card border border-line rounded-xl p-4">
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
                        <div class="p-1 rounded-md bg-card shadow-sm">
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
