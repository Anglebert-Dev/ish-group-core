{{-- One licence per site, then support renewals (docs/self-hosting-build-guide.md §11). --}}
<div class="grid gap-6 lg:grid-cols-2 p-5">
    {{-- Licence --}}
    <section class="space-y-3">
        <div>
            <p class="font-semibold text-ink">{{ __('distribution::distribution.licence_card') }}</p>
            <p class="text-xs text-muted">{{ __('distribution::distribution.licence_how') }}</p>
        </div>

        @if ($site->is_test)
            <p class="text-sm text-muted">{{ __('distribution::distribution.licence_not_needed') }}</p>
        @elseif ($licence)
            @include('distribution::sites.partials.invoice', ['charge' => $licence])
        @else
            <form action="{{ route('superadmin.distribution.sites.charges.store', $site) }}" method="POST" class="rounded-xl border border-line p-4 space-y-3">
                @csrf
                <input type="hidden" name="kind" value="licence">
                <p class="text-sm text-muted">{{ __('distribution::distribution.licence_none') }}</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <x-common.input name="amount" type="number" step="0.01" min="0" :label="__('distribution::distribution.charge_amount')" required />
                    <x-common.input name="support_months" type="number" min="0" max="60" :value="\Modules\Distribution\Services\LicenceService::DEFAULT_SUPPORT_MONTHS" :label="__('distribution::distribution.charge_support_months')" />
                    <x-common.input name="due_days" type="number" min="0" max="180" value="14" :label="__('distribution::distribution.charge_due_days')" />
                </div>
                <p class="text-xs text-muted">{{ __('distribution::distribution.charge_support_months_hint') }}</p>
                <x-common.input name="description" :label="__('distribution::distribution.charge_description')" :hint="__('distribution::distribution.charge_description_hint')" />
                <x-common.button type="submit" size="sm">{{ __('distribution::distribution.licence_create') }}</x-common.button>
            </form>
        @endif
    </section>

    {{-- Support & updates --}}
    <section class="space-y-3">
        <div>
            <p class="font-semibold text-ink">{{ __('distribution::distribution.support_card') }}</p>
            <p class="text-xs text-muted">{{ __('distribution::distribution.support_until_hint') }}</p>
        </div>

        <div class="rounded-xl border border-line p-4 flex flex-wrap items-center justify-between gap-3">
            <x-common.badge :color="$supportColor">{{ $supportText }}</x-common.badge>
            @if ($site->hasSupport() && ! $site->is_test)
                <span class="text-xs text-muted">{{ __('distribution::distribution.support_days_left', ['days' => $site->supportDaysLeft()]) }}</span>
            @endif
        </div>

        @unless ($site->is_test)
            @if ($renewal)
                @include('distribution::sites.partials.invoice', ['charge' => $renewal])
            @elseif ($site->licencePaid())
                @php $renewFrom = $site->support_until && $site->support_until->isFuture() ? $site->support_until->copy()->addDay() : today(); @endphp
                <form action="{{ route('superadmin.distribution.sites.charges.store', $site) }}" method="POST" class="rounded-xl border border-line p-4 space-y-3">
                    @csrf
                    <input type="hidden" name="kind" value="support">
                    <p class="text-sm font-semibold text-ink">{{ __('distribution::distribution.support_renew') }}</p>
                    <p class="text-xs text-muted">{{ __('distribution::distribution.support_renew_hint', ['date' => $renewFrom->format('d M Y')]) }}</p>
                    <div class="grid sm:grid-cols-3 gap-3">
                        <x-common.input name="amount" id="renew-amount" type="number" step="0.01" min="0" :label="__('distribution::distribution.charge_amount')" required />
                        <x-common.input name="support_months" id="renew-support_months" type="number" min="1" max="60" :value="\Modules\Distribution\Services\LicenceService::DEFAULT_SUPPORT_MONTHS" :label="__('distribution::distribution.charge_support_months')" />
                        <x-common.input name="due_days" id="renew-due_days" type="number" min="0" max="180" value="14" :label="__('distribution::distribution.charge_due_days')" />
                    </div>
                    <x-common.button type="submit" size="sm">{{ __('distribution::distribution.support_renew') }}</x-common.button>
                </form>
            @else
                <p class="text-sm text-muted">{{ __('distribution::distribution.renewal_needs_licence') }}</p>
            @endif

            <details class="rounded-xl border border-line">
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-ink">{{ __('distribution::distribution.support_more') }}</summary>
                <div class="px-4 pb-4 space-y-5">
                    <div class="space-y-2">
                        <p class="text-xs text-muted">{{ __('distribution::distribution.reminder_hint') }}</p>
                        @if ($site->support_until)
                            <form action="{{ route('superadmin.distribution.sites.support.remind', $site) }}" method="POST">
                                @csrf
                                <x-common.button type="submit" variant="secondary" size="sm">{{ __('distribution::distribution.reminder_send_now') }}</x-common.button>
                            </form>
                        @endif
                    </div>
                    <form action="{{ route('superadmin.distribution.sites.support', $site) }}" method="POST" class="space-y-3 border-t border-line pt-4">
                        @csrf
                        @method('PUT')
                        <p class="font-semibold text-ink text-sm">{{ __('distribution::distribution.support_change') }}</p>
                        <p class="text-xs text-muted">{{ __('distribution::distribution.support_change_hint') }}</p>
                        <x-common.input name="support_until" type="date" :label="__('distribution::distribution.support_until')" :value="$site->support_until?->toDateString()" />
                        <x-common.checkbox name="no_support" value="1" inline :label="__('distribution::distribution.support_none')"
                            onchange="var d = this.form.querySelector('[name=support_until]'); d.disabled = this.checked; if (this.checked) d.value = '';" />
                        <x-common.input name="reason" :label="__('distribution::distribution.support_change_reason')" required />
                        <x-common.button type="submit" variant="secondary" size="sm">{{ __('distribution::distribution.support_change_save') }}</x-common.button>
                    </form>
                </div>
            </details>
        @endunless
    </section>
</div>

{{-- Every invoice, with each step that happened to it --}}
@if ($site->charges->isNotEmpty())
    <div class="border-t border-line p-5">
        <p class="font-semibold text-ink">{{ __('distribution::distribution.history_title') }}</p>
        <p class="text-xs text-muted mb-3">{{ __('distribution::distribution.history_hint') }}</p>
        <div class="divide-y divide-line border border-line rounded-xl">
            @foreach ($site->charges as $charge)
                <details>
                    <summary class="cursor-pointer px-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span class="font-medium text-ink">{{ $charge->number }}</span>
                        <span class="text-muted">{{ __('distribution::distribution.charge_kind_'.$charge->kind) }}</span>
                        <span class="text-muted">{{ $charge->issued_on->format('d M Y') }}</span>
                        <span class="font-semibold text-ink">{{ money($charge->amount) }}</span>
                        <x-common.badge :color="$charge->status->color()">{{ $charge->status->label() }}</x-common.badge>
                    </summary>
                    <ol class="px-4 pb-4 space-y-2 text-sm">
                        @forelse ($history[$charge->id] ?? [] as $event)
                            @php $props = (array) $event->properties; @endphp
                            <li class="flex gap-3">
                                <span class="text-xs text-muted whitespace-nowrap w-32 shrink-0 pt-0.5">{{ $event->created_at?->format('d M Y H:i') }}</span>
                                <div class="min-w-0">
                                    <p class="text-ink">
                                        {{ __('distribution::distribution.event_'.$event->action, ['email' => $props['email'] ?? '', 'reason' => $props['reason'] ?? '']) }}
                                        @if ($event->actor_name || $event->user)<span class="text-xs text-muted">{{ __('distribution::distribution.event_by', ['name' => $event->actor_name ?: $event->user?->name]) }}</span>@endif
                                    </p>
                                    @if ($event->action === 'distribution_charge_created' && isset($props['amount']))
                                        <p class="text-xs text-muted">{{ __('distribution::distribution.change_amount') }}: {{ money($props['amount']) }}</p>
                                    @endif
                                    @if ($event->action === 'distribution_charge_paid')
                                        <p class="text-xs text-muted">{{ __('distribution::distribution.charge_paid_meta', ['date' => $props['paid_on'] ?? '—', 'method' => ! empty($props['payment_method']) ? __('distribution::distribution.method_'.$props['payment_method']) : '—', 'reference' => ($props['payment_reference'] ?? null) ?: '—']) }}</p>
                                    @endif
                                    @foreach ((array) ($props['changes'] ?? []) as $field => $change)
                                        <p class="text-xs text-muted break-words">
                                            {{ __('distribution::distribution.change_'.$field) }}:
                                            <span class="line-through">{{ $field === 'amount' && $change['from'] !== null ? money($change['from']) : ($change['from'] ?? '—') }}</span>
                                            → <span class="text-ink">{{ $field === 'amount' && $change['to'] !== null ? money($change['to']) : ($change['to'] ?? '—') }}</span>
                                        </p>
                                    @endforeach
                                </div>
                            </li>
                        @empty
                            <li class="text-xs text-muted">{{ __('distribution::distribution.history_empty') }}</li>
                        @endforelse
                        @if ($charge->note)
                            <li class="text-xs text-muted whitespace-pre-line">{{ $charge->note }}</li>
                        @endif
                    </ol>
                </details>
            @endforeach
        </div>
    </div>
@endif
