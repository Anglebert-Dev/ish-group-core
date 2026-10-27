<x-layouts.app title="{{ $role->display_name }}">
    <div data-details-page>
        <x-common.details.header
            :title="$role->display_name"
            :subtitle="__('body.roles_description')"
            :back-href="route('roles.index')"
            :back-label="__('body.back')"
        >
            <x-slot:meta>
                <span class="text-xs px-2 py-1 rounded font-semibold bg-brand-light text-brand-dark">{{ count($assigned) }} {{ __('body.permissions') }}</span>
            </x-slot:meta>
            <x-slot:actions>
                @if (! $role->is_default)
                    <x-common.button href="{{ route('roles.edit', $role) }}" variant="secondary" size="sm">{{ __('body.edit') }}</x-common.button>
                @endif
            </x-slot:actions>
        </x-common.details.header>

        <x-common.details.summary>
            <x-common.details.summary-item :label="__('body.permissions')" :value="(string) count($assigned)" />
            <x-common.details.summary-item :label="__('body.role')" :value="$role->display_name" />
        </x-common.details.summary>

        <x-common.details.section
            :title="__('body.permissions')"
            icon="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
        >
            @php
                $moduleLabels = $catalogue['module_labels'] ?? [];
                $controllerLabels = $catalogue['controller_labels'] ?? [];
            @endphp

            @foreach ($catalogue['modules'] as $module => $controllers)
                @php
                    $modulePermissions = collect($controllers)->flatten()->filter(fn ($p) => in_array($p, $assigned, true));
                @endphp
                @if ($modulePermissions->isNotEmpty())
                    <div class="mb-5 last:mb-0">
                        <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ $moduleLabels[$module] ?? Str::headline($module) }}</p>
                        <div class="space-y-3">
                            @foreach ($controllers as $controller => $permissions)
                                @php
                                    $granted = collect($permissions)->filter(fn ($p) => in_array($p, $assigned, true));
                                @endphp
                                @continue($granted->isEmpty())
                                <div class="p-4 bg-surface rounded-lg">
                                    <p class="text-sm font-medium text-ink mb-2">{{ $controllerLabels[$controller] ?? Str::headline($controller) }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($granted as $permission)
                                            @php $action = Str::before($permission, ' '); @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-brand-light text-brand-dark">{{ Str::headline($action) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            @php $extraGranted = array_intersect($catalogue['extra'], $assigned); @endphp
            @if (! empty($extraGranted))
                <div>
                    <p class="text-xs font-semibold text-muted uppercase tracking-wide mb-2">{{ __('body.other_permissions') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($extraGranted as $permission)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-brand-light text-brand-dark">{{ Str::headline($permission) }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (empty($assigned))
                <p class="text-sm text-muted">{{ __('body.no_permissions_assigned') }}</p>
            @endif
        </x-common.details.section>
    </div>
</x-layouts.app>
