<x-layouts.app :title="$site->name">
    @include('distribution::partials.nav')

    <x-common.page-header :title="$site->name" :description="$site->customer_name">
        <x-slot:actions>
            <x-common.button href="{{ route('superadmin.distribution.sites.edit', $site) }}" size="sm" variant="secondary">{{ __('body.edit') }}</x-common.button>
        </x-slot:actions>
    </x-common.page-header>

    <div class="grid gap-6 lg:grid-cols-3 mb-6">
        <div class="bg-card border border-line rounded-xl p-5 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('body.status') }}</span><x-common.badge :color="$site->status->color()">{{ $site->status->label() }}</x-common.badge></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.update_mode') }}</span><span>{{ $site->update_mode->label() }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.support_until') }}</span>
                @if (! $site->support_until)
                    <span>{{ __('distribution::distribution.support_no_end') }}</span>
                @elseif (! $site->hasSupport())
                    <x-common.badge color="danger">{{ __('distribution::distribution.support_ended_on', ['date' => $site->support_until->format('d M Y')]) }}</x-common.badge>
                @else
                    <x-common.badge :color="$site->supportDaysLeft() <= 30 ? 'warning' : 'success'">{{ $site->support_until->format('d M Y') }}</x-common.badge>
                @endif
            </div>
            @if ($site->is_test)
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.is_test') }}</span><x-common.badge color="warning">{{ __('distribution::distribution.test_site') }}</x-common.badge></div>
            @endif
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.app_url') }}</span><span class="truncate">{{ $site->app_url ?: '—' }}</span></div>
            <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.contact_email') }}</span><span class="truncate">{{ $site->contact_email ?: '—' }}</span></div>
        </div>
        <div class="bg-card border border-line rounded-xl p-5 space-y-2 text-sm lg:col-span-2">
            <p class="font-semibold text-ink mb-1">{{ __('distribution::distribution.reported') }}</p>
            <div class="grid sm:grid-cols-2 gap-x-8 gap-y-2">
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.last_check_in') }}</span><span>{{ $site->last_check_in_at?->diffForHumans() ?? __('distribution::distribution.never') }}</span></div>
                @php $latestCore = \Modules\Distribution\Models\Release::latestFor([\Modules\Distribution\Models\Release::CORE], $site->is_test)[\Modules\Distribution\Models\Release::CORE] ?? null; @endphp
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.core_version') }}</span>
                    <span>{{ $site->core_version ?: '—' }}
                        @if ($latestCore && version_compare($latestCore->version, \Modules\Distribution\Models\Release::plainVersion($site->core_version), '>'))
                            <span class="text-warning">→ {{ $latestCore->version }} {{ __('distribution::distribution.core_available') }}</span>
                        @endif
                    </span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.php_version') }}</span><span>{{ $site->php_version ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.db_driver') }}</span><span>{{ $site->db_driver ?: '—' }}</span></div>
                <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.modules_writable') }}</span>
                    <span>{{ $site->modules_writable === null ? '—' : ($site->modules_writable ? __('distribution::distribution.yes') : __('distribution::distribution.no')) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Licence & support (docs/self-hosting-build-guide.md §11) --}}
    <div id="licence" class="bg-card border border-line rounded-xl overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-line flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-ink">{{ __('distribution::distribution.licence_title') }}</p>
                <p class="text-xs text-muted">{{ __('distribution::distribution.licence_hint') }}</p>
            </div>
            @if ($site->support_until)
                <form action="{{ route('superadmin.distribution.sites.support.remind', $site) }}" method="POST" class="shrink-0">
                    @csrf
                    <x-common.button type="submit" variant="secondary" size="sm">{{ __('distribution::distribution.reminder_send_now') }}</x-common.button>
                </form>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.charge_number') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('distribution::distribution.charge_description') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('distribution::distribution.charge_amount') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($site->charges as $charge)
                        <tr class="align-top">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <p class="font-medium text-ink">{{ $charge->number }}</p>
                                <p class="text-xs text-muted">{{ $charge->issued_on->format('d M Y') }} · {{ __('distribution::distribution.charge_kind_'.$charge->kind) }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-body">{{ $charge->description }}</p>
                                @if ($charge->period_until)
                                    <p class="text-xs text-muted">{{ __('distribution::distribution.print_period', ['from' => $charge->period_from?->format('d M Y'), 'until' => $charge->period_until->format('d M Y')]) }}</p>
                                @endif
                                @if ($charge->paid_on)
                                    <p class="text-xs text-muted">{{ __('distribution::distribution.charge_paid_meta', ['date' => $charge->paid_on->format('d M Y'), 'method' => $charge->payment_method ? __('distribution::distribution.method_'.$charge->payment_method) : '—', 'reference' => $charge->payment_reference ?: '—']) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap font-semibold text-ink">{{ money($charge->amount) }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <x-common.badge :color="$charge->status->color()">{{ $charge->status->label() }}</x-common.badge>
                                @if ($charge->isOpen() && $charge->due_on?->isPast())
                                    <x-common.badge color="danger">{{ __('distribution::distribution.charge_overdue') }}</x-common.badge>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col items-start gap-2">
                                    <div class="flex flex-wrap gap-3 text-xs font-semibold">
                                        <a href="{{ route('print.show', ['distribution_charge', $charge->getRouteKey()]) }}" target="_blank" rel="noopener" class="text-brand hover:text-brand-dark">{{ __('body.print') }}</a>
                                        <a href="{{ route('print.pdf', ['distribution_charge', $charge->getRouteKey()]) }}" class="text-brand hover:text-brand-dark">PDF</a>
                                    </div>
                                    <form action="{{ route('superadmin.distribution.sites.charges.email', [$site, $charge]) }}" method="POST" class="flex gap-2">
                                        @csrf
                                        <input type="email" name="email" value="{{ $site->contact_email }}" required placeholder="{{ __('distribution::distribution.contact_email') }}" class="h-8 w-44 rounded-lg border border-line bg-card px-2 text-xs">
                                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('distribution::distribution.charge_email') }}</x-common.button>
                                    </form>
                                    @if ($charge->emailed_at)
                                        <p class="text-xs text-muted">{{ __('distribution::distribution.charge_emailed', ['when' => $charge->emailed_at->diffForHumans()]) }}</p>
                                    @endif
                                    @if ($charge->isOpen())
                                        <details>
                                            <summary class="cursor-pointer text-xs font-semibold text-success">{{ __('distribution::distribution.charge_mark_paid') }}</summary>
                                            <form action="{{ route('superadmin.distribution.sites.charges.pay', [$site, $charge]) }}" method="POST" class="mt-2 space-y-2">
                                                @csrf
                                                <x-common.input name="paid_on" type="date" :label="__('distribution::distribution.charge_paid_on')" :value="today()->toDateString()" required />
                                                <x-common.select name="payment_method" :label="__('distribution::distribution.charge_method')" :options="collect(\Modules\Distribution\Models\Charge::METHODS)->mapWithKeys(fn ($m) => [$m => __('distribution::distribution.method_'.$m)])->all()" />
                                                <x-common.input name="payment_reference" :label="__('distribution::distribution.charge_reference')" />
                                                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.charge_mark_paid') }}</x-common.button>
                                            </form>
                                        </details>
                                        <details>
                                            <summary class="cursor-pointer text-xs font-semibold text-danger">{{ __('distribution::distribution.charge_cancel') }}</summary>
                                            <form action="{{ route('superadmin.distribution.sites.charges.cancel', [$site, $charge]) }}" method="POST" class="mt-2 space-y-2">
                                                @csrf
                                                <x-common.input name="reason" :label="__('distribution::distribution.charge_cancel_reason')" required />
                                                <x-common.button type="submit" variant="danger" size="sm">{{ __('distribution::distribution.charge_cancel') }}</x-common.button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-muted">{{ __('distribution::distribution.charges_none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid gap-6 lg:grid-cols-2 p-5 border-t border-line">
            <form action="{{ route('superadmin.distribution.sites.charges.store', $site) }}" method="POST" class="space-y-3">
                @csrf
                <p class="font-semibold text-ink text-sm">{{ __('distribution::distribution.charge_new') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <x-common.select name="kind" :label="__('distribution::distribution.charge_kind')" hide-placeholder required
                        :value="$site->charges->isEmpty() ? 'licence' : 'support'"
                        :options="collect(\Modules\Distribution\Models\Charge::KINDS)->mapWithKeys(fn ($k) => [$k => __('distribution::distribution.charge_kind_'.$k)])->all()" />
                    <x-common.input name="amount" type="number" step="0.01" min="0" :label="__('distribution::distribution.charge_amount')" required />
                    <x-common.input name="support_months" type="number" min="0" max="60" :value="\Modules\Distribution\Services\LicenceService::DEFAULT_SUPPORT_MONTHS" :label="__('distribution::distribution.charge_support_months')" :hint="__('distribution::distribution.charge_support_months_hint')" />
                    <x-common.input name="due_days" type="number" min="0" max="180" value="14" :label="__('distribution::distribution.charge_due_days')" />
                </div>
                <x-common.input name="description" :label="__('distribution::distribution.charge_description')" :hint="__('distribution::distribution.charge_description_hint')" />
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.charge_create') }}</x-common.button>
            </form>

            <form action="{{ route('superadmin.distribution.sites.support', $site) }}" method="POST" class="space-y-3">
                @csrf
                @method('PUT')
                <p class="font-semibold text-ink text-sm">{{ __('distribution::distribution.support_change') }}</p>
                <p class="text-xs text-muted">{{ __('distribution::distribution.support_change_hint') }}</p>
                <x-common.input name="support_until" type="date" :label="__('distribution::distribution.support_until')" :value="$site->support_until?->toDateString()" />
                <x-common.input name="reason" :label="__('distribution::distribution.support_change_reason')" required />
                <x-common.button type="submit" variant="secondary" size="sm">{{ __('distribution::distribution.support_change_save') }}</x-common.button>
            </form>
        </div>
    </div>

    {{-- Modules --}}
    <div class="bg-card border border-line rounded-xl overflow-hidden mb-6">
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
    <div class="bg-card border border-line rounded-xl overflow-hidden mb-6">
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
    <div class="bg-card border border-line rounded-xl overflow-hidden mb-6">
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
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.build_zip') }}</x-common.button>
            </form>
        </div>


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

    {{-- Reported by the site --}}
    <div class="grid gap-6 lg:grid-cols-2 mb-6">
        <div class="bg-card border border-line rounded-xl overflow-hidden">
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
        <div class="bg-card border border-line rounded-xl overflow-hidden">
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
    <div class="bg-card border border-line rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-line">
            <p class="font-semibold text-ink">{{ __('distribution::distribution.tokens') }}</p>
            <p class="text-sm text-muted">{{ __('distribution::distribution.tokens_hint') }}</p>
        </div>

        @if (session('link_key'))
            <div class="m-5 rounded-xl border border-warning/30 bg-warning/10 px-5 py-4">
                <p class="text-sm text-ink">{{ __('distribution::distribution.link_key_once') }}</p>
                <p class="mt-2 font-mono text-xs break-all text-ink select-all bg-card border border-warning/30 rounded-lg p-3">{{ session('link_key') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('superadmin.distribution.sites.link-key', $site) }}" class="p-5 border-b border-line space-y-3">
            @csrf
            <p class="text-sm text-muted">{{ __('distribution::distribution.link_key_hint') }}</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[240px]">
                    <x-common.input name="email" type="email" :label="__('distribution::distribution.email_label')" :value="$site->contact_email" :hint="__('distribution::distribution.link_key_email_hint')" />
                </div>
                <x-common.button type="submit" size="sm" variant="secondary">{{ __('distribution::distribution.link_key') }}</x-common.button>
            </div>
            <input type="hidden" name="revoke_others" value="0">
            <x-common.checkbox name="revoke_others" value="1" :checked="true" inline :label="__('distribution::distribution.link_key_revoke_others')" />
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-line">
                    @forelse ($site->tokens as $token)
                        <tr>
                            <td class="px-5 py-3.5"><span class="font-mono">ish_…{{ $token->last4 }}</span> <span class="text-xs text-muted">({{ $token->build_id ? __('distribution::distribution.token_from_zip') : __('distribution::distribution.token_from_link') }})</span></td>
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
