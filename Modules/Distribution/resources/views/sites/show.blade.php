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
                            <td class="px-5 py-3.5">
                                @if (isset($latest[$row->module]))
                                    {{ $latest[$row->module]->version }}
                                @else
                                    <span class="text-warning font-medium">{{ __('distribution::distribution.not_released') }}</span>
                                    <div class="text-xs text-muted mt-0.5 max-w-xs">{{ __('distribution::distribution.not_released_yet') }}</div>
                                    <x-common.action-button :action="route('superadmin.distribution.releases.store')" class="mt-1">
                                        <x-slot:fields><input type="hidden" name="module" value="{{ $row->module }}"></x-slot:fields>
                                        {{ __('distribution::distribution.publish_now') }}
                                    </x-common.action-button>
                                @endif
                            </td>
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

    {{-- Customer's server --}}
    @php
        $server = (array) $site->server_details;
        $serverProblems = \Modules\Distribution\Support\ServerDetails::problems($server);
    @endphp
    <div class="bg-white border border-line rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-line flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-semibold text-ink">{{ __('distribution::distribution.server_details') }}</p>
                <p class="text-sm text-muted max-w-2xl">{{ __('distribution::distribution.server_details_hint') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-common.button :href="route('superadmin.distribution.sites.guide', $site)" size="sm" variant="secondary" target="_blank">{{ __('distribution::distribution.setup_guide') }}</x-common.button>
                <x-common.button :href="route('superadmin.distribution.sites.server.template', $site)" size="sm" variant="secondary">{{ __('distribution::distribution.server_template') }}</x-common.button>
                <x-common.button :href="route('superadmin.distribution.sites.server.edit', $site)" size="sm" variant="secondary">{{ __('distribution::distribution.server_edit') }}</x-common.button>
            </div>
        </div>

        @if ($serverProblems)
            <div class="m-5 rounded-xl border border-red-200 bg-red-50 text-red-800 px-5 py-4 text-sm">
                <p class="font-semibold">{{ __('distribution::distribution.server_problems') }}</p>
                <ul class="list-disc pl-5 mt-1">@foreach ($serverProblems as $problem)<li>{{ $problem }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($server)
            <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-2 p-5 text-sm">
                @foreach ($server as $key => $answer)
                    <div class="flex justify-between gap-3 border-b border-line/60 py-1">
                        <dt class="text-muted">{{ __('distribution::distribution.server_'.$key) }}</dt>
                        <dd class="text-ink text-right break-all">{{ \Modules\Distribution\Support\ServerDetails::display($key, $answer) }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="p-5 text-sm text-muted">{{ __('distribution::distribution.server_none') }}</p>
        @endif

        <form method="POST" action="{{ route('superadmin.distribution.sites.server.upload', $site) }}" enctype="multipart/form-data" class="border-t border-line p-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[240px]">
                <label class="block text-sm font-semibold text-ink mb-1.5" for="server-csv">{{ __('distribution::distribution.server_upload') }}</label>
                <input id="server-csv" type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold">
            </div>
            <x-common.button type="submit" size="sm">{{ __('distribution::distribution.server_upload') }}</x-common.button>
        </form>
    </div>

    {{-- Zips --}}
    <div class="bg-white border border-line rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-line flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-semibold text-ink">{{ __('distribution::distribution.builds') }}</p>
                <p class="text-sm text-muted max-w-2xl">{{ __('distribution::distribution.builds_hint') }}</p>
            </div>
            <form method="POST" action="{{ route('superadmin.distribution.sites.builds.store', $site) }}"
                  onsubmit="var b=this.querySelector('button'); b.disabled=true; b.textContent=@js(__('distribution::distribution.building_wait'));">
                @csrf
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.build_zip') }}</x-common.button>
            </form>
        </div>

        @if (session('install_code'))
            <div class="m-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <p class="text-sm text-amber-900">{{ __('distribution::distribution.install_code_once') }}</p>
                <p class="mt-2 font-mono text-2xl font-bold tracking-widest text-ink">{{ session('install_code') }}</p>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($site->builds as $build)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $build->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3.5">{{ implode(', ', (array) $build->modules) }}</td>
                            <td class="px-5 py-3.5 font-mono text-xs">{{ $build->core_version ?: '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $build->size ? number_format($build->size / 1048576, 1).' MB' : '' }}</td>
                            <td class="px-5 py-3.5">
                                <x-common.badge :color="$build->status->color()">{{ $build->status->label() }}</x-common.badge>
                                @if ($build->error)<div class="text-xs text-danger mt-1 max-w-md break-words">{{ $build->error }}</div>@endif
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                @if ($build->isDownloadable())
                                    <x-common.action-button :href="route('superadmin.distribution.sites.builds.download', [$site, $build])">{{ __('distribution::distribution.download') }}</x-common.action-button>
                                @endif
                                <x-common.action-button :action="route('superadmin.distribution.sites.builds.destroy', [$site, $build])" method="DELETE" color="danger"
                                    data-confirm data-confirm-title="{{ __('distribution::distribution.delete_build_confirm') }}" data-confirm-variant="danger">
                                    {{ __('body.delete') }}
                                </x-common.action-button>
                                @if ($build->isDownloadable())
                                    <div class="text-xs text-muted mt-1">{{ __('distribution::distribution.expires') }} {{ $build->expires_at->format('d M Y') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_builds') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Reported by the site --}}
    <div class="grid gap-6 lg:grid-cols-2 mb-6">
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('distribution::distribution.update_history') }}</p></div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($site->updateReports as $report)
                    <li class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-ink">{{ $report->module }} {{ in_array($report->action, ['disable', 'enable'], true) ? __('distribution::distribution.action_'.$report->action) : ($report->from_version ? $report->from_version.' → ' : '').$report->to_version }}</span>
                            <x-common.badge :color="$report->status === 'success' ? 'success' : 'danger'">{{ __('distribution::distribution.report_'.$report->status) }}</x-common.badge>
                        </div>
                        <div class="text-xs text-muted mt-0.5">{{ $report->created_at?->format('d M Y H:i') }}</div>
                        @if ($report->error)<div class="text-xs text-danger mt-1 break-words">{{ $report->error }}</div>@endif
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_update_history') }}</li>
                @endforelse
            </ul>
        </div>
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-line"><p class="font-semibold text-ink">{{ __('distribution::distribution.recent_check_ins') }}</p></div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($site->checkIns as $checkIn)
                    <li class="px-5 py-3 flex items-center justify-between gap-3">
                        <span class="text-muted whitespace-nowrap">{{ $checkIn->created_at?->format('d M Y H:i') }}</span>
                        <span class="text-right">{{ $checkIn->offered ? __('distribution::distribution.offered').': '.implode(', ', $checkIn->offered) : __('distribution::distribution.nothing_offered') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.no_check_ins') }}</li>
                @endforelse
            </ul>
        </div>
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
