<x-layouts.app :title="$module['name']">
    <x-superadmin.nav />

    <x-common.page-header :title="$module['name']" :description="\Illuminate\Support\Facades\Lang::has('body.module_category_'.$module['category']) ? __('body.module_category_'.$module['category']) : \Illuminate\Support\Str::headline($module['category'])">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.modules.index') }}" variant="secondary" size="sm">← {{ __('body.modules') }}</x-common.button>
            @if ($openUrl)
                <x-common.button href="{{ $openUrl }}" size="sm">{{ __('body.module_open') }}</x-common.button>
            @endif
        </x-slot:actions>
    </x-common.page-header>

    @if ($module['off_here'])
        <div class="mb-6 rounded-xl border border-warning/30 bg-warning/10 px-5 py-4 text-sm text-ink flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold">{{ __('body.module_off_here') }}</p>
                <p>{{ __('body.module_off_here_hint') }}</p>
            </div>
            <x-common.button href="{{ route('superadmin.businesses.show', auth('staff')->user()->business) }}" size="sm" variant="secondary">{{ __('body.module_off_here_button') }}</x-common.button>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3 items-start">
        <div class="lg:col-span-2 space-y-6">
            <section class="bg-card border border-line rounded-xl p-5">
                <h2 class="font-display font-bold text-ink mb-2">{{ __('body.module_about') }}</h2>
                <p class="text-sm text-body leading-relaxed">{{ __($module['description']) ?: '—' }}</p>
                @if ($module['keywords'])
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach ($module['keywords'] as $keyword)
                            <x-common.badge color="muted">{{ $keyword }}</x-common.badge>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="bg-card border border-line rounded-xl p-5">
                <h2 class="font-display font-bold text-ink mb-2">{{ __('body.module_needs') }}</h2>
                @forelse ($requirements as $required)
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-line last:border-0 text-sm">
                        <a href="{{ route('superadmin.modules.show', $required['name']) }}" class="font-medium text-link hover:underline">{{ $required['name'] }}</a>
                        @include('app.super-admin.modules.partials.status', ['module' => $required])
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('body.module_needs_nothing') }}</p>
                @endforelse
                @if ($module['needed_by'])
                    <p class="text-xs text-muted mt-3">{{ __('body.module_needed_by_list', ['modules' => implode(', ', $module['needed_by'])]) }}</p>
                @endif
            </section>

            <section class="bg-card border border-line rounded-xl p-5">
                <h2 class="font-display font-bold text-ink mb-3">{{ __('body.module_adds') }}</h2>
                <dl class="grid gap-5 sm:grid-cols-2">
                    @foreach (['pages', 'permissions', 'documents', 'tables'] as $part)
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted mb-1">{{ __('body.module_adds_'.$part) }}</dt>
                            <dd class="text-sm text-body break-words {{ $part === 'tables' ? 'font-mono text-xs' : '' }}">
                                @if ($adds[$part])
                                    <ul class="space-y-0.5">@foreach ($adds[$part] as $item)<li>{{ $item }}</li>@endforeach</ul>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <aside class="bg-card border border-line rounded-xl p-5 space-y-3 text-sm">
            <div class="flex items-center justify-between gap-3"><span class="text-muted">{{ __('body.status') }}</span>@include('app.super-admin.modules.partials.status')</div>
            <div class="flex items-center justify-between gap-3"><span class="text-muted">{{ __('body.version') }}</span>
                <span class="text-ink">{{ $module['installed_version'] ?? $module['available_version'] }}
                    @if ($module['update_available'])<span class="text-warning font-semibold">→ {{ $module['available_version'] }}</span>@endif
                </span>
            </div>
            <div class="flex items-center justify-between gap-3"><span class="text-muted">{{ __('body.module_billing') }}</span>
                <x-common.badge :color="$module['billable'] ? 'brand' : 'muted'">{{ $module['billable_locked'] ? __('body.module_free_always') : ($module['billable'] ? __('body.module_in_packages') : __('body.module_free')) }}</x-common.badge>
            </div>
            <div class="flex items-center justify-between gap-3"><span class="text-muted">{{ __('body.module_businesses') }}</span>
                <span class="text-ink">{{ ! $module['active'] ? '—' : ($businessesUsing ?? __('body.module_businesses_all')) }}</span>
            </div>

            <div class="pt-3 border-t border-line flex flex-col items-start gap-2">
                @if (! $module['installed'])
                    @if ($module['missing_requirements'])
                        <p class="text-xs font-semibold text-warning">{{ __('body.module_needs_first', ['modules' => implode(', ', $module['missing_requirements'])]) }}</p>
                    @else
                        <x-common.action-button :action="route('superadmin.modules.install', $module['name'])" color="success">{{ __('body.install') }}</x-common.action-button>
                    @endif
                @else
                    @if ($module['update_available'])
                        <x-common.action-button :action="route('superadmin.modules.update', $module['name'])" color="brand">{{ __('body.update') }}</x-common.action-button>
                    @endif
                    @php $cascade = $module['enabled'] && $module['needed_by']; @endphp
                    <x-common.action-button :action="route('superadmin.modules.toggle', $module['name'])" :color="$module['enabled'] ? 'warning' : 'success'"
                        :data-confirm="$cascade"
                        :data-confirm-title="$cascade ? __('body.disable').' '.$module['name'].'?' : false"
                        :data-confirm-message="$cascade ? __('body.module_disable_cascade', ['modules' => implode(', ', $module['needed_by'])]) : false"
                        :data-confirm-variant="$cascade ? 'danger' : false">
                        <x-slot:fields><input type="hidden" name="enabled" value="{{ $module['enabled'] ? 0 : 1 }}"></x-slot:fields>
                        {{ $module['enabled'] ? __('body.disable') : __('body.enable') }}
                    </x-common.action-button>
                @endif
                @unless ($module['billable_locked'])
                    <x-common.action-button :action="route('superadmin.modules.billable', $module['name'])" color="brand">
                        <x-slot:fields><input type="hidden" name="billable" value="{{ $module['billable'] ? 0 : 1 }}"></x-slot:fields>
                        {{ $module['billable'] ? __('body.module_make_free') : __('body.module_make_billable') }}
                    </x-common.action-button>
                @endunless
                @if ($module['installed'])
                    <x-common.action-button :action="route('superadmin.modules.uninstall', $module['name'])" color="danger"
                        data-confirm
                        data-confirm-title="{{ __('body.uninstall') }} {{ $module['name'] }}?"
                        data-confirm-message="{{ __('body.uninstall_module_warning') }}"
                        data-confirm-variant="danger"
                        data-confirm-text="{{ __('body.uninstall') }}">
                        {{ __('body.uninstall') }}
                    </x-common.action-button>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
