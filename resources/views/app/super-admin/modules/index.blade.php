<x-layouts.super-admin :title="__('body.modules')">
    <x-common.page-header :title="__('body.modules')" :description="__('body.modules_description')" />

    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line">
                    <th class="px-5 py-3 font-semibold">{{ __('body.module') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.version') }}</th>
                    <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                    <th class="px-5 py-3 font-semibold text-right">{{ __('body.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($modules as $module)
                    <tr class="align-top">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink">{{ $module['name'] }}</p>
                            @if ($module['description'])
                                <p class="text-xs text-muted mt-0.5 max-w-md">{{ $module['description'] }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap text-muted">
                            @if ($module['installed'])
                                {{ $module['installed_version'] }}
                                @if ($module['update_available'])
                                    <span class="text-warning">→ {{ $module['available_version'] }}</span>
                                @endif
                            @else
                                {{ $module['available_version'] }}
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if ($module['active'])
                                <x-common.badge color="success">{{ __('body.active') }}</x-common.badge>
                            @elseif ($module['installed'])
                                <x-common.badge color="warning">{{ __('body.disabled') }}</x-common.badge>
                            @else
                                <x-common.badge color="muted">{{ __('body.not_installed') }}</x-common.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                            @if (! $module['installed'])
                                <form action="{{ route('superadmin.modules.install', $module['name']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-success hover:opacity-80 font-medium text-sm">{{ __('body.install') }}</button>
                                </form>
                            @else
                                @if ($module['update_available'])
                                    <form action="{{ route('superadmin.modules.update', $module['name']) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-brand hover:text-brand-dark font-medium text-sm">{{ __('body.update') }}</button>
                                    </form>
                                    <span class="text-line">|</span>
                                @endif
                                <form action="{{ route('superadmin.modules.toggle', $module['name']) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="enabled" value="{{ $module['enabled'] ? 0 : 1 }}">
                                    <button type="submit" class="text-brand hover:text-brand-dark font-medium text-sm">{{ $module['enabled'] ? __('body.disable') : __('body.enable') }}</button>
                                </form>
                                <span class="text-line">|</span>
                                <form action="{{ route('superadmin.modules.uninstall', $module['name']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-danger hover:opacity-80 font-medium text-sm"
                                        data-confirm
                                        data-confirm-title="{{ __('body.uninstall') }} {{ $module['name'] }}?"
                                        data-confirm-message="{{ __('body.uninstall_module_warning') }}"
                                        data-confirm-variant="danger"
                                        data-confirm-text="{{ __('body.uninstall') }}"
                                    >{{ __('body.uninstall') }}</button>
                                </form>
                            @endif
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
</x-layouts.super-admin>
