<div>
        <div class="px-5 py-4 border-b border-line">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.modules') }}</p>
            <p class="text-sm text-muted">
                {{ $site->last_check_in_at
                    ? __('distribution::distribution.reported_at', ['time' => $site->last_check_in_at->format('d M Y H:i')])
                    : __('distribution::distribution.not_reported_yet') }}
            </p>
            @if ($site->last_check_in_at && $site->builds->first(fn ($build) => $build->status->value === 'ready' && $build->created_at->gt($site->last_check_in_at)))
                <p class="text-sm text-warning mt-1">{{ __('distribution::distribution.reported_before_zip') }}</p>
            @endif
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
                                @elseif ($row->installed_version)
                                    <span class="text-muted">{{ __('distribution::distribution.no_release_needed') }}</span>
                                    <div class="text-xs text-muted mt-0.5 max-w-xs">{{ __('distribution::distribution.no_release_needed_hint') }}</div>
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
                                @if ($row->held)<x-common.badge color="warning">{{ __('distribution::distribution.module_held') }}</x-common.badge>@endif
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
