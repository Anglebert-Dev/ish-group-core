<x-layouts.app :title="__('body.modules')">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.modules')" :description="__('body.modules_description')" />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                    <th class="px-5 py-3 font-semibold">{{ __('body.module') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.version') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($modules as $module)
                    <tr class="align-top hover:bg-surface">
                        <td class="px-5 py-4">
                            <p class="font-medium text-ink">{{ $module['name'] }}</p>
                            @if ($module['description'])
                                <p class="text-xs text-muted mt-1 max-w-xl leading-relaxed">{{ $module['description'] }}</p>
                            @endif
                            @if ($module['requires'])
                                <p class="text-xs font-semibold text-brand mt-1">{{ __('body.module_requires_list', ['modules' => implode(', ', $module['requires'])]) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap text-muted">
                            @if ($module['installed'])
                                {{ $module['installed_version'] }}
                                @if ($module['update_available'])
                                    <span class="block text-xs text-warning font-semibold">{{ __('body.update_to', ['version' => $module['available_version']]) }}</span>
                                @endif
                            @else
                                {{ $module['available_version'] }}
                            @endif
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            @if ($module['active'])
                                <x-common.badge color="success">{{ __('body.active') }}</x-common.badge>
                            @elseif ($module['installed'])
                                <x-common.badge color="warning">{{ __('body.disabled') }}</x-common.badge>
                            @else
                                <x-common.badge color="muted">{{ __('body.not_installed') }}</x-common.badge>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-col items-start gap-1.5">
                                @if (! $module['installed'])
                                    <x-common.action-button :action="route('superadmin.modules.install', $module['name'])" color="success">{{ __('body.install') }}</x-common.action-button>
                                @else
                                    @if ($module['update_available'])
                                        <x-common.action-button :action="route('superadmin.modules.update', $module['name'])" color="brand">{{ __('body.update') }}</x-common.action-button>
                                    @endif
                                    <x-common.action-button :action="route('superadmin.modules.toggle', $module['name'])" :color="$module['enabled'] ? 'warning' : 'success'">
                                        <x-slot:fields><input type="hidden" name="enabled" value="{{ $module['enabled'] ? 0 : 1 }}"></x-slot:fields>
                                        {{ $module['enabled'] ? __('body.disable') : __('body.enable') }}
                                    </x-common.action-button>
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
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('body.no_modules_found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <p class="text-sm text-muted mt-4">{{ __('body.modules_cli_hint') }}</p>
</x-layouts.app>
