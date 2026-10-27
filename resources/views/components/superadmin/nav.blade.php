@php
    $tabs = [
        ['label' => __('body.overview'), 'route' => 'superadmin.dashboard', 'active' => 'superadmin.dashboard'],
        ['label' => __('body.businesses'), 'route' => 'superadmin.businesses.index', 'active' => 'superadmin.businesses.*'],
        ['label' => __('body.modules'), 'route' => 'superadmin.modules.index', 'active' => 'superadmin.modules.*'],
    ];
    // Modules add platform tabs with DataController::superadmin_menu().
    foreach (app(\App\Services\Module\ModuleManager::class)->collectHook('superadmin_menu') as $tab) {
        if (is_array($tab) && isset($tab['label'], $tab['route']) && Route::has($tab['route'])) {
            $tabs[] = $tab + ['active' => $tab['route']];
        }
    }
    $tabs[] = ['label' => __('body.communicator'), 'route' => 'superadmin.communicator.index', 'active' => 'superadmin.communicator.*'];
    $tabs[] = ['label' => __('body.platform_settings'), 'route' => 'superadmin.settings.index', 'active' => 'superadmin.settings.*'];
@endphp

{{-- Sub-navigation shown on every platform page. --}}
<nav class="bg-white border border-line rounded-xl mb-6 overflow-x-auto">
    <div class="flex items-stretch min-w-max">
        <div class="flex items-center gap-2 px-5 py-3.5 text-ink font-display font-semibold border-r border-line">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            {{ __('body.super_admin') }}
        </div>
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['active']); @endphp
            <a href="{{ route($tab['route']) }}"
               class="flex items-center px-5 py-3.5 text-sm font-medium transition-colors {{ $active ? 'bg-surface text-ink' : 'text-muted hover:text-ink hover:bg-surface/60' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
