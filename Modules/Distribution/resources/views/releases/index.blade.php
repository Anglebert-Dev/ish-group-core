<x-layouts.app :title="__('distribution::distribution.releases')">
    @include('distribution::partials.nav')

    <x-common.page-header :title="__('distribution::distribution.releases')" :description="__('distribution::distribution.releases_description')" />

    @if (session('signing_secret'))
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="font-semibold text-amber-900">{{ __('distribution::distribution.secret_backup_title') }}</p>
            <p class="text-sm text-amber-900 mt-1">{{ __('distribution::distribution.secret_backup_hint') }}</p>
            <p class="mt-3 font-mono text-xs break-all bg-white border border-amber-200 rounded-lg p-3 select-all">{{ session('signing_secret') }}</p>
        </div>
    @endif
    @if (session('signing_env_lines'))
        <pre class="mb-6 rounded-xl border border-line bg-surface p-4 font-mono text-xs whitespace-pre-wrap break-all select-all">{{ session('signing_env_lines') }}</pre>
    @endif

    @unless ($keysConfigured)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-5 py-4 text-sm flex flex-wrap items-center justify-between gap-3">
            <span>{{ __('distribution::distribution.keys_missing') }}</span>
            <form method="POST" action="{{ route('superadmin.distribution.releases.key') }}">
                @csrf
                <x-common.button type="submit" size="sm" data-confirm data-confirm-title="{{ __('distribution::distribution.create_key_confirm') }}">{{ __('distribution::distribution.create_key') }}</x-common.button>
            </form>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
        <form method="POST" action="{{ route('superadmin.distribution.releases.store') }}" class="bg-white border border-line rounded-xl p-5 space-y-4 lg:col-span-2">
            @csrf
            <div>
                <p class="font-semibold text-ink">{{ __('distribution::distribution.publish_release') }}</p>
                <p class="text-sm text-muted">{{ __('distribution::distribution.publish_hint') }}</p>
            </div>
            <x-common.select name="module" :label="__('distribution::distribution.module')" hide-placeholder required
                :options="collect($modules)->mapWithKeys(fn ($m) => [$m['name'] => $m['name'].' — '.__('distribution::distribution.in_code').' '.$m['version'].' · '.__('distribution::distribution.published_version').' '.($m['published'] ?? '—')])->all()" />
            <x-common.textarea name="changelog" :label="__('distribution::distribution.changelog')" :rows="3" />
            @if ($testSites)
                <x-common.checkbox name="test_only" value="1" :checked="true" :label="__('distribution::distribution.publish_test_first')"
                    :description="trans_choice('distribution::distribution.publish_test_first_hint', $testSites, ['count' => $testSites])" />
            @else
                <p class="text-xs text-muted">{{ __('distribution::distribution.publish_no_test_sites') }}</p>
            @endif
            <x-common.button type="submit" :disabled="! $keysConfigured">{{ __('distribution::distribution.publish') }}</x-common.button>
        </form>

        <div class="bg-white border border-line rounded-xl p-5 text-sm space-y-2">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.signing_key') }}</p>
            @if ($publicKey)
                <p class="text-muted">{{ __('distribution::distribution.public_key_hint') }}</p>
                <p class="font-mono text-xs break-all bg-surface border border-line rounded-lg p-3">{{ $publicKey }}</p>
            @else
                <p class="text-muted">{{ __('distribution::distribution.create_key_hint') }}</p>
                <form method="POST" action="{{ route('superadmin.distribution.releases.key') }}">
                    @csrf
                    <x-common.button type="submit" size="sm" data-confirm data-confirm-title="{{ __('distribution::distribution.create_key_confirm') }}">{{ __('distribution::distribution.create_key') }}</x-common.button>
                </form>
            @endif
        </div>
    </div>

    @if ($releases->isEmpty())
        <x-common.empty-state :title="__('distribution::distribution.no_releases')" :description="__('distribution::distribution.no_releases_hint')" />
    @else
        <div class="bg-white border border-line rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.module') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.version') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.published_at') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.size') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.changelog') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($releases as $release)
                            <tr class="{{ $release->yanked_at ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3.5 font-medium text-ink">{{ $release->module }}</td>
                                <td class="px-5 py-3.5 font-mono">{{ $release->version }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $release->published_at?->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ number_format($release->size / 1024, 0) }} KB</td>
                                <td class="px-5 py-3.5 text-muted">{{ $release->changelog ?: '—' }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    @if ($release->yanked_at)
                                        <x-common.badge color="muted">{{ __('distribution::distribution.withdrawn') }}</x-common.badge>
                                    @else
                                        @if ($release->test_only)
                                            <x-common.badge color="warning">{{ __('distribution::distribution.test_sites_only') }}</x-common.badge>
                                            <x-common.action-button :action="route('superadmin.distribution.releases.everyone', $release)" color="success"
                                                data-confirm data-confirm-title="{{ __('distribution::distribution.release_to_everyone_confirm', ['module' => $release->module, 'version' => $release->version]) }}">
                                                {{ __('distribution::distribution.release_to_everyone') }}
                                            </x-common.action-button>
                                        @endif
                                        <x-common.action-button :action="route('superadmin.distribution.releases.yank', $release)" color="danger"
                                            data-confirm data-confirm-title="{{ __('distribution::distribution.yank_confirm', ['module' => $release->module, 'version' => $release->version]) }}" data-confirm-variant="danger">
                                            {{ __('distribution::distribution.yank') }}
                                        </x-common.action-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4"><x-common.pagination :paginator="$releases" /></div>
    @endif
</x-layouts.app>
