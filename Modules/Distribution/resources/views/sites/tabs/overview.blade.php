@php
    $serverProblems = \Modules\Distribution\Support\ServerDetails::problems((array) $site->server_details);
    $lastZip = $site->builds->first(fn ($build) => $build->status->value === 'ready');
    $sentZip = $site->builds->first(fn ($build) => $build->emailed_at);
    $steps = [
        ['label' => __('distribution::distribution.step_modules'), 'tab' => 'modules',
            'done' => $site->modules->isNotEmpty(),
            'text' => $site->modules->isNotEmpty() ? __('distribution::distribution.step_modules_done', ['modules' => $site->modules->pluck('module')->implode(', ')]) : __('distribution::distribution.step_modules_todo')],
        ['label' => __('distribution::distribution.step_licence'), 'tab' => 'licence',
            'done' => $site->licencePaid(),
            'text' => match (true) {
                $site->is_test => __('distribution::distribution.step_licence_test'),
                ! $licence => __('distribution::distribution.step_licence_none'),
                $licence->status === $paidStatus => __('distribution::distribution.step_licence_paid', ['date' => $licence->paid_on?->format('d M Y'), 'number' => $licence->number]),
                default => __('distribution::distribution.step_licence_open', ['number' => $licence->number, 'date' => $licence->due_on?->format('d M Y') ?? '—']),
            }],
        ['label' => __('distribution::distribution.step_server'), 'tab' => 'install', 'optional' => true,
            'done' => $site->server_details && ! $serverProblems,
            'text' => ! $site->server_details ? __('distribution::distribution.step_server_todo') : ($serverProblems ? __('distribution::distribution.step_server_problems') : __('distribution::distribution.step_server_done'))],
        ['label' => __('distribution::distribution.step_zip'), 'tab' => 'install',
            'done' => (bool) $lastZip,
            'text' => $lastZip ? __('distribution::distribution.step_zip_done', ['date' => $lastZip->created_at->format('d M Y H:i')]) : ($site->licencePaid() ? __('distribution::distribution.step_zip_todo') : __('distribution::distribution.step_zip_blocked'))],
        ['label' => __('distribution::distribution.step_sent'), 'tab' => 'install',
            'done' => (bool) $sentZip,
            'text' => $sentZip ? __('distribution::distribution.step_sent_done', ['email' => $sentZip->emailed_to, 'date' => $sentZip->emailed_at->format('d M Y H:i')]) : __('distribution::distribution.step_sent_todo')],
        ['label' => __('distribution::distribution.step_installed'), 'tab' => 'updates',
            'done' => (bool) $site->installed_at,
            'text' => $site->installed_at ? __('distribution::distribution.step_installed_done', ['date' => $site->installed_at->format('d M Y H:i')]) : __('distribution::distribution.step_installed_todo')],
        ['label' => __('distribution::distribution.step_support'), 'tab' => 'licence',
            'done' => $site->hasSupport(),
            'text' => $supportText],
    ];

    // The last zip went out standalone: that copy never reports back, so
    // these steps can't be followed from here (a licence on record still shows).
    $handedOver = $lastZip?->standalone;
    if ($handedOver) {
        $steps = array_values(array_filter($steps, fn ($step) => ! in_array($step['label'], [
            __('distribution::distribution.step_installed'),
            __('distribution::distribution.step_support'),
            ...($licence ? [] : [__('distribution::distribution.step_licence')]),
        ], true)));
    }
@endphp

<div class="grid gap-6 lg:grid-cols-3 p-5">
    <div class="lg:col-span-2">
        <p class="font-semibold text-ink">{{ __('distribution::distribution.steps_title') }}</p>
        <p class="text-xs text-muted mb-3">{{ $handedOver ? __('distribution::distribution.steps_standalone') : __('distribution::distribution.steps_hint') }}</p>
        <ol class="divide-y divide-line border border-line rounded-xl">
            @foreach ($steps as $i => $step)
                <li class="flex items-start gap-3 px-4 py-3">
                    <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $step['done'] ? 'bg-success text-on-success' : 'border border-line text-muted' }}">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-ink">{{ $step['label'] }}</p>
                        <p class="text-xs text-muted break-words">{{ $step['text'] }}</p>
                    </div>
                    <button type="button" data-go-tab="{{ $step['tab'] }}" class="shrink-0 text-xs font-semibold text-link hover:underline">{{ __('distribution::distribution.step_go') }}</button>
                </li>
            @endforeach
        </ol>
    </div>

    <div class="space-y-2 text-sm">
        <p class="font-semibold text-ink mb-1">{{ __('distribution::distribution.site_details') }}</p>
        <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.update_mode') }}</span><span>{{ $site->update_mode->label() }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.is_test') }}</span><span>{{ $site->is_test ? __('distribution::distribution.yes') : __('distribution::distribution.no') }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.app_url') }}</span><span class="truncate">{{ $site->app_url ?: '—' }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted">{{ __('distribution::distribution.contact_email') }}</span><span class="truncate">{{ $site->contact_email ?: '—' }}</span></div>
    </div>
</div>

@once
<script>
// "Open" on a step: the same as clicking that tab.
document.addEventListener('click', function (e) {
    var go = e.target.closest('[data-go-tab]');
    if (!go) return;
    var tab = document.querySelector('[data-details-tab="' + go.getAttribute('data-go-tab') + '"]');
    if (tab) { tab.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
});
</script>
@endonce
