    @php
        $server = (array) $site->server_details;
        $serverProblems = \Modules\Distribution\Support\ServerDetails::problems($server);
    @endphp
    <div class="border-b border-line">
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
            <div class="m-5 rounded-xl border border-danger/30 bg-danger/10 text-ink px-5 py-4 text-sm">
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
                <input id="server-csv" type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border file:border-line file:bg-card file:px-3 file:py-2 file:text-sm file:font-semibold">
            </div>
            <x-common.button type="submit" size="sm">{{ __('distribution::distribution.server_upload') }}</x-common.button>
        </form>

        <form method="POST" action="{{ route('superadmin.distribution.sites.email.preparation', $site) }}" class="border-t border-line p-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[240px]">
                <x-common.input name="email" type="email" :label="__('distribution::distribution.email_label')" :value="old('email', $site->contact_email)" :hint="__('distribution::distribution.email_prepare_hint')" required />
            </div>
            <x-common.button type="submit" size="sm" variant="secondary">{{ __('distribution::distribution.email_prepare_button') }}</x-common.button>
        </form>
    </div>

    {{-- Zips --}}
    @if (session('signing_secret'))
        <div class="m-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-ink">
            <p class="font-semibold">{{ __('distribution::distribution.keys_created_web') }}</p>
            <p class="mt-2 font-mono text-xs break-all select-all bg-card border border-warning/30 rounded-lg p-3">{{ session('signing_secret') }}</p>
        </div>
    @endif
    @unless ($site->licencePaid())
        <div class="m-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-ink">
            <p class="font-semibold">{{ __('distribution::distribution.licence_unpaid_title') }}</p>
            <p>{{ __('distribution::distribution.build_needs_paid_licence') }}
                <button type="button" data-go-tab="licence" class="font-semibold text-link hover:underline">{{ __('distribution::distribution.tab_licence') }} →</button></p>
        </div>
    @endunless
    @unless ($signingReady)
        <div class="m-5 rounded-xl border border-warning/30 bg-warning/10 p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-ink max-w-2xl">
                <p class="font-semibold">{{ __('distribution::distribution.signing_needed_title') }}</p>
                <p>{{ __('distribution::distribution.signing_needed_body') }}</p>
            </div>
            <form method="POST" action="{{ route('superadmin.distribution.releases.key') }}">
                @csrf
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.signing_create') }}</x-common.button>
            </form>
        </div>
    @endunless
    <div>
        <div class="px-5 py-4 border-b border-line flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-semibold text-ink">{{ __('distribution::distribution.builds') }}</p>
                <p class="text-sm text-muted max-w-2xl">{{ __('distribution::distribution.builds_hint') }}</p>
                <p class="text-sm mt-2">
                    {{ __('distribution::distribution.zip_contacts') }}
                    <span class="font-mono font-semibold text-ink">{{ rtrim((string) config('app.url'), '/') }}</span>
                </p>
                <p class="text-xs text-muted max-w-2xl">{{ __('distribution::distribution.zip_contacts_hint') }}</p>
                @if (app()->environment('production') && ! str_starts_with((string) config('app.url'), 'https://'))
                    <p class="text-sm text-danger mt-1 max-w-2xl">{{ __('distribution::distribution.zip_contacts_not_https') }}</p>
                @endif
            </div>
            <form method="POST" action="{{ route('superadmin.distribution.sites.builds.store', $site) }}"
                  onsubmit="var b=this.querySelector('button'); b.disabled=true; b.textContent=@js(__('distribution::distribution.building_wait'));">
                @csrf
                <div class="flex flex-wrap items-center gap-3">
                    <x-common.checkbox name="developer" value="1" inline :label="__('distribution::distribution.build_developer')" title="{{ __('distribution::distribution.build_developer_hint') }}" />
                    <x-common.button type="submit" size="sm">{{ __('distribution::distribution.build_zip') }}</x-common.button>
                </div>
            </form>
        </div>


        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($site->builds as $build)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $build->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3.5">
                                {{ implode(', ', (array) $build->modules) }}
                                @if ($build->developer)<x-common.badge color="brand" class="ml-1">{{ __('distribution::distribution.build_developer_badge') }}</x-common.badge>@endif
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs">{{ $build->core_version ?: '—' }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-muted">{{ $build->size ? number_format($build->size / 1048576, 1).' MB' : '' }}</td>
                            <td class="px-5 py-3.5">
                                <x-common.badge :color="$build->status->color()">{{ $build->status->label() }}</x-common.badge>
                                @if ($build->error)<div class="text-xs text-danger mt-1 max-w-md break-words">{{ $build->error }}</div>@endif
                                @if ($build->isDownloadable() && $build->install_code)
                                    <div class="text-xs text-muted mt-1">{{ __('distribution::distribution.install_code_label') }}: <span class="font-mono font-semibold text-ink select-all">{{ $build->install_code }}</span></div>
                                @endif
                                @if ($build->emailed_at)
                                    <div class="text-xs text-success mt-1">{{ __('distribution::distribution.emailed', ['email' => $build->emailed_to, 'time' => $build->emailed_at->format('d M Y H:i')]) }}</div>
                                @endif
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
                                    @if ($build->install_code)
                                        <form method="POST" action="{{ route('superadmin.distribution.sites.builds.email', [$site, $build]) }}" class="mt-2 flex items-center justify-end gap-2">
                                            @csrf
                                            <input type="email" name="email" value="{{ $build->emailed_to ?: $site->contact_email }}" required placeholder="{{ __('distribution::distribution.email_label') }}"
                                                   class="h-8 w-48 rounded-lg border border-line px-2 text-xs" aria-label="{{ __('distribution::distribution.email_label') }}">
                                            <x-common.button type="submit" size="sm" title="{{ __('distribution::distribution.email_package_hint') }}">{{ __('distribution::distribution.email_package_button') }}</x-common.button>
                                        </form>
                                    @endif
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
