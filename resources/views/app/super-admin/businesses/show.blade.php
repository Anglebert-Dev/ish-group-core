@php
    $enabled = $business->enabled_modules;
    $isOwnBusiness = auth('staff')->user()->business_id === $business->id;
    $icon = [
        'building' => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h1m-1 4h1m-1 4h1',
        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'toggle' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'mail' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'login' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
        'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 10-8 0 4 4 0 008 0z',
        'shield' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'key' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
    ];
@endphp

<x-layouts.app :title="$business->name">
    <x-superadmin.nav />

    <x-common.page-header :title="__('body.view_business')" :description="$business->name">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.businesses.index') }}" variant="secondary" size="sm">← {{ __('body.businesses') }}</x-common.button>
            @unless ($isOwnBusiness && $business->is_active)
                <form action="{{ route('superadmin.businesses.active', $business) }}" method="POST">
                    @csrf @method('PUT')
                    <input type="hidden" name="is_active" value="{{ $business->is_active ? 0 : 1 }}">
                    <x-common.button type="submit" :variant="$business->is_active ? 'secondary' : 'primary'" size="sm"
                        data-confirm
                        data-confirm-title="{{ $business->is_active ? __('body.deactivate') : __('body.activate') }} {{ $business->name }}?"
                        data-confirm-message="{{ $business->is_active ? __('body.deactivate_business_warning') : '' }}"
                        data-confirm-variant="{{ $business->is_active ? 'danger' : 'primary' }}"
                    >{{ $business->is_active ? __('body.deactivate') : __('body.activate') }}</x-common.button>
                </form>
            @endunless
        </x-slot:actions>
    </x-common.page-header>

    <div class="space-y-6">
        {{-- Overview cards --}}
        <div class="bg-white border border-line rounded-xl p-5">
            <p class="flex items-center gap-2 font-display font-bold text-ink mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon['building'] }}"/></svg>
                {{ $business->name }}
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-common.info-card :title="__('body.business')" :rows="[
                    [__('body.business_name'), $business->name, $icon['building']],
                    [__('body.timezone'), $business->timezone ?: config('app.timezone'), $icon['clock']],
                    [__('body.status'), $business->is_active ? __('body.active') : __('body.inactive'), $icon['toggle']],
                    [__('body.created'), \App\Libraries\GeneralLibrary::formatDateTime($business->created_at), $icon['calendar']],
                ]" />
                <x-common.info-card :title="__('body.owner')" :rows="[
                    [__('body.full_name'), $business->owner?->name, $icon['user']],
                    [__('body.email_address'), $business->owner?->email, $icon['mail']],
                    [__('body.last_login'), $business->owner?->last_login_at ? \App\Libraries\GeneralLibrary::formatDateTime($business->owner->last_login_at) : null, $icon['login']],
                ]" />
                <x-common.info-card :title="__('body.figures')" :rows="[
                    [__('body.users'), $stats['users'].' ('.__('body.n_active', ['n' => $stats['active_users']]).')', $icon['users']],
                    [__('body.roles'), (string) $stats['roles'], $icon['shield']],
                    [__('body.last_activity'), $stats['last_login'] ? \App\Libraries\GeneralLibrary::formatDateTime($stats['last_login']) : null, $icon['clock']],
                ]" />
            </div>
        </div>

        {{-- Module panels, e.g. the subscription (DataController::superadmin_business_panel) --}}
        @foreach (app(\App\Services\Module\ModuleManager::class)->hook('superadmin_business_panel', $business) as $panel)
            @if (filled($panel))
                {!! $panel !!}
            @endif
        @endforeach

        {{-- Users --}}
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line">
                <h2 class="font-display font-bold text-ink">{{ __('body.all_users') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                            <th class="px-5 py-3 font-semibold">{{ __('body.full_name') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.email_address') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.role') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.last_login') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($users as $user)
                            <tr class="align-top">
                                <td class="px-5 py-3.5 font-medium text-ink whitespace-nowrap">
                                    {{ $user->name }}
                                    @if ($user->isSuperAdmin())
                                        <x-common.badge color="brand" class="ml-1">{{ __('body.super_admin') }}</x-common.badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 whitespace-nowrap">{{ $user->email }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap">{{ $user->roleName() ?? '—' }}</td>
                                <td class="px-5 py-3.5"><x-common.status :status="ucfirst($user->status->value)" /></td>
                                <td class="px-5 py-3.5 text-muted whitespace-nowrap">@datetime($user->last_login_at)</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" data-password-dialog
                                            data-url="{{ route('superadmin.users.password', $user->uuid) }}"
                                            data-name="{{ $user->name }}"
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border bg-white px-2.5 py-1 text-xs font-semibold transition-colors border-brand/40 text-brand hover:bg-brand-light">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon['key'] }}"/></svg>
                                            {{ __('body.update_password') }}
                                        </button>
                                        @if (! $user->isSuperAdmin() && $user->isApproved() && $business->is_active)
                                            <x-common.action-button :action="route('superadmin.impersonate', $user->uuid)" color="success" :icon="$icon['login']"
                                                data-confirm
                                                data-confirm-title="{{ __('body.impersonate_confirm_title', ['name' => $user->name]) }}"
                                                data-confirm-message="{{ __('body.impersonate_confirm_message') }}"
                                                data-confirm-text="{{ __('body.impersonate') }}">
                                                {{ __('body.impersonate_user', ['email' => $user->email]) }}
                                            </x-common.action-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-muted">{{ __('body.no_users') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Modules: per-business switches only when no billing module decides --}}
            @if ($modulesFromPackage)
                <div class="bg-white border border-line rounded-xl p-5 space-y-2">
                    <h2 class="font-display font-bold text-ink">{{ __('body.business_modules') }}</h2>
                    <p class="text-sm text-muted">{{ __('body.business_modules_from_package') }}</p>
                </div>
            @else
                <form action="{{ route('superadmin.businesses.modules', $business) }}" method="POST" class="bg-white border border-line rounded-xl p-5 space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <h2 class="font-display font-bold text-ink">{{ __('body.business_modules') }}</h2>
                        <p class="text-sm text-muted mt-1">{{ __('body.business_modules_hint') }}</p>
                    </div>
                    @forelse ($modules as $module)
                        <x-common.checkbox name="modules[]" :value="$module['name']" :label="$module['name']"
                            :description="trim($module['description'].($module['requires'] ? ' '.__('body.module_requires_list', ['modules' => implode(', ', $module['requires'])]).'.' : ''))"
                            :checked="$enabled === null || in_array($module['name'], $enabled, true)" />
                    @empty
                        <p class="text-sm text-muted">{{ __('body.no_active_modules') }}</p>
                    @endforelse
                    @if ($modules->isNotEmpty())
                        <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
                    @endif
                </form>
            @endif

            {{-- Danger zone --}}
            @unless ($isOwnBusiness)
                <form id="delete-business" action="{{ route('superadmin.businesses.destroy', $business) }}" method="POST" class="bg-white border border-danger/40 rounded-xl p-5 space-y-4 scroll-mt-24">
                    @csrf @method('DELETE')
                    <div>
                        <h2 class="font-display font-bold text-danger">{{ __('body.delete_business') }}</h2>
                        <p class="text-sm text-muted mt-1">{{ __('body.delete_business_warning') }}</p>
                    </div>
                    <x-common.input name="confirm_name" :label="__('body.delete_business_confirm', ['name' => $business->name])" required autocomplete="off" />
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-danger hover:opacity-90 text-white font-semibold text-sm px-4 py-2">
                        {{ __('body.delete_business_permanently') }}
                    </button>
                </form>
            @endunless
        </div>
    </div>

    {{-- One password dialog shared by every "Update password" button. --}}
    <dialog id="password-dialog" class="rounded-2xl p-0 w-[min(28rem,calc(100vw-2rem))] backdrop:bg-ink/50">
        <form method="POST" class="p-6 space-y-4" data-password-form>
            @csrf @method('PUT')
            <div>
                <h3 class="font-display font-bold text-ink">{{ __('body.update_password') }}</h3>
                <p class="text-sm text-muted mt-1" data-password-name></p>
            </div>
            <x-common.input type="password" name="password" :label="__('body.new_password')" required autocomplete="new-password" />
            <x-common.input type="password" name="password_confirmation" :label="__('body.confirm_password')" required autocomplete="new-password" />
            <div class="flex justify-end gap-2 pt-2">
                <x-common.button type="button" variant="secondary" size="sm" data-password-close>{{ __('body.cancel') }}</x-common.button>
                <x-common.button type="submit" size="sm">{{ __('body.save') }}</x-common.button>
            </div>
        </form>
    </dialog>

    @push('scripts')
        <script>
            (function () {
                var dialog = document.getElementById('password-dialog');
                var form = dialog.querySelector('[data-password-form]');
                document.querySelectorAll('[data-password-dialog]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        form.action = button.dataset.url;
                        form.reset();
                        dialog.querySelector('[data-password-name]').textContent = button.dataset.name;
                        dialog.showModal();
                    });
                });
                dialog.querySelector('[data-password-close]').addEventListener('click', function () { dialog.close(); });
            })();
        </script>
    @endpush
</x-layouts.app>
