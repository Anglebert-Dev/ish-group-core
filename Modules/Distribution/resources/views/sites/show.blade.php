<x-layouts.app :title="$site->name">
    @include('distribution::partials.nav')

    <x-common.page-header :title="$site->name" :description="$site->customer_name">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.distribution.sites.edit', $site) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
        <div class="bg-white border border-line rounded-xl p-5 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('body.status') }}</span><x-common.badge :color="$site->status->color()">{{ $site->status->label() }}</x-common.badge></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.update_mode') }}</span><span>{{ $site->update_mode->label() }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.app_url') }}</span><span class="truncate">{{ $site->app_url ?: '—' }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.contact_email') }}</span><span class="truncate">{{ $site->contact_email ?: '—' }}</span></div>
        </div>
        <div class="bg-white border border-line rounded-xl p-5 space-y-2 text-sm lg:col-span-2">
            <p class="font-semibold text-ink mb-1">{{ __('distribution::distribution.reported') }}</p>
            <div class="grid sm:grid-cols-2 gap-x-8 gap-y-2">
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.last_check_in') }}</span><span>{{ $site->last_check_in_at?->diffForHumans() ?? __('distribution::distribution.never') }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.core_version') }}</span><span>{{ $site->core_version ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.php_version') }}</span><span>{{ $site->php_version ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.db_driver') }}</span><span>{{ $site->db_driver ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.modules_writable') }}</span>
                    <span>{{ $site->modules_writable === null ? '—' : ($site->modules_writable ? __('distribution::distribution.yes') : __('distribution::distribution.no')) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Modules --}}
    <div class="bg-white border border-line rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-line">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.modules') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.module') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.installed_version') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.latest_release') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($site->modules as $row)
                        <tr>
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $row->module }}</td>
                            <td class="px-5 py-3.5">{{ $row->installed_version ?: '—' }}</td>
                            <td class="px-5 py-3.5">{{ $latest[$row->module]->version ?? __('distribution::distribution.not_released') }}</td>
                            <td class="px-5 py-3.5">
                                <x-common.badge :color="$row->status->color()">{{ $row->status->label() }}</x-common.badge>
                                @if ($row->last_error)<div class="text-xs text-danger mt-1">{{ $row->last_error }}</div>@endif
                            </td>
                            <td class="px-5 py-3.5">
                                <x-common.action-button :action="route('superadmin.distribution.sites.modules.unassign', [$site, $row->module])" method="DELETE" color="danger"
                                    data-confirm data-confirm-title="{{ __('distribution::distribution.take_away_confirm', ['module' => $row->module]) }}" data-confirm-variant="danger">
                                    {{ __('distribution::distribution.take_away') }}
                                </x-common.action-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-muted">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('superadmin.distribution.sites.modules.assign', $site) }}" class="border-t border-line p-5 space-y-3">
            @csrf
            <p class="text-sm font-semibold text-ink">{{ __('distribution::distribution.give_modules') }}</p>
            @if ($assignable)
                <p class="text-sm text-muted">{{ __('distribution::distribution.modules_hint') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($assignable as $module)
                        <x-common.checkbox name="modules[]" :value="$module" :label="$module" />
                    @endforeach
                </div>
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.give') }}</x-common.button>
            @else
                <p class="text-sm text-muted">{{ __('distribution::distribution.all_modules_given') }}</p>
            @endif
        </form>
    </div>

    {{-- Tokens --}}
    <div class="bg-white border border-line rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-line">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.tokens') }}</p>
            <p class="text-sm text-muted">{{ __('distribution::distribution.tokens_hint') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($site->tokens as $token)
                        <tr>
                            <td class="px-5 py-3.5 font-mono">ish_…{{ $token->last4 }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ __('distribution::distribution.issued') }} {{ $token->created_at?->format('d M Y') }}</td>
                            <td class="px-5 py-3.5 text-muted whitespace-nowrap">{{ __('distribution::distribution.last_used') }}: {{ $token->last_used_at?->diffForHumans() ?? __('distribution::distribution.never') }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($token->isRevoked())
                                    <x-common.badge color="muted">{{ __('distribution::distribution.revoked') }}</x-common.badge>
                                @else
                                    <x-common.action-button :action="route('superadmin.distribution.sites.tokens.revoke', [$site, $token])" color="danger"
                                        data-confirm data-confirm-title="{{ __('distribution::distribution.revoke_confirm') }}" data-confirm-variant="danger">
                                        {{ __('distribution::distribution.revoke') }}
                                    </x-common.action-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_tokens') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
