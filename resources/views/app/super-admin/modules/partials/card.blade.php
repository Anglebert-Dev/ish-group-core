@php
    $state = $module['update_available'] ? 'update' : ($module['active'] ? 'active' : ($module['installed'] ? 'off' : 'available'));
@endphp
<article data-module-card data-state="{{ $state }}" data-search="{{ mb_strtolower($module['name'].' '.$module['description'].' '.implode(' ', $module['keywords'])) }}"
         class="bg-card border border-line rounded-xl p-5 flex flex-col gap-3">
    <div class="flex items-start justify-between gap-3">
        <a href="{{ route('superadmin.modules.show', $module['name']) }}" class="font-semibold text-ink hover:text-brand break-words min-w-0">{{ $module['name'] }}</a>
        <span class="shrink-0">@include('app.super-admin.modules.partials.status')</span>
    </div>

    <p class="text-sm text-muted leading-relaxed line-clamp-3">{{ __($module['description']) }}</p>

    <div class="space-y-1 text-xs">
        @if ($module['missing_requirements'] && ! $module['installed'])
            <p class="font-semibold text-warning">{{ __('body.module_needs_first', ['modules' => implode(', ', $module['missing_requirements'])]) }}</p>
        @elseif ($module['requires'])
            <p class="text-muted">{{ __('body.module_requires_list', ['modules' => implode(', ', $module['requires'])]) }}</p>
        @endif
        @if ($module['off_here'])
            <p class="font-semibold text-warning">{{ __('body.module_off_here') }}</p>
        @endif
    </div>

    <div class="mt-auto pt-3 border-t border-line flex items-center justify-between gap-3">
        <span class="text-xs text-muted min-w-0">
            {{ $module['installed_version'] ?? $module['available_version'] }}
            @if ($module['update_available'])<span class="text-warning font-semibold">→ {{ $module['available_version'] }}</span>@endif
            · {{ $module['billable_locked'] ? __('body.module_free_always') : ($module['billable'] ? __('body.module_in_packages') : __('body.module_free')) }}
        </span>
        <div class="flex items-center gap-3 shrink-0">
            @if ($module['update_available'])
                <x-common.action-button :action="route('superadmin.modules.update', $module['name'])" color="brand">{{ __('body.update') }}</x-common.action-button>
            @elseif (! $module['installed'] && ! $module['missing_requirements'])
                <x-common.action-button :action="route('superadmin.modules.install', $module['name'])" color="success">{{ __('body.install') }}</x-common.action-button>
            @endif
            <a href="{{ route('superadmin.modules.show', $module['name']) }}" class="text-sm font-semibold text-link hover:underline">{{ __('body.module_view') }}</a>
        </div>
    </div>
</article>
