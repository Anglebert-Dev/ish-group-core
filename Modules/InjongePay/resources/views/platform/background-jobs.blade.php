<x-layouts.app :title="__('injongepay::injongepay.menu_background_jobs')">
    <x-common.page-header :title="__('injongepay::injongepay.menu_background_jobs')" :description="__('injongepay::injongepay.background_jobs_description')" />
    @include('injongepay::partials.test-mode')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-card border border-line rounded-xl p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.jobs_scheduler') }}</h2>
                <x-common.badge :color="$schedulerSeen && $schedulerSeen->gte(now()->subMinutes($graceMinutes)) ? 'success' : 'danger'">{{ $schedulerSeen && $schedulerSeen->gte(now()->subMinutes($graceMinutes)) ? __('injongepay::injongepay.jobs_all_well') : __('injongepay::injongepay.jobs_needs_action') }}</x-common.badge>
            </div>
            <p class="text-sm text-muted mt-2">{{ $schedulerSeen ? __('injongepay::injongepay.jobs_scheduler_last_seen', ['time' => $schedulerSeen->diffForHumans()]) : __('injongepay::injongepay.jobs_scheduler_never') }}</p>
        </div>
        <div class="bg-card border border-line rounded-xl p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display font-bold text-ink">{{ __('injongepay::injongepay.jobs_queue') }}</h2>
                @php $queueStuck = collect($problems)->contains(fn ($p) => str_contains($p['command'], 'queue:work')); @endphp
                <x-common.badge :color="$queueStuck ? 'danger' : 'success'">{{ $queueStuck ? __('injongepay::injongepay.jobs_needs_action') : __('injongepay::injongepay.jobs_all_well') }}</x-common.badge>
            </div>
            <p class="text-sm text-muted mt-2">{{ __('injongepay::injongepay.jobs_queue_ok', ['minutes' => $graceMinutes]) }}</p>
        </div>
    </div>

    @foreach ($problems as $problem)
        <div class="rounded-xl border border-danger/40 bg-danger/10 p-3 mb-4 text-sm text-ink">
            <p class="font-semibold">{{ $problem['title'] }}</p>
            <p class="text-body mt-1">{{ __('body.health_start') }} <code class="rounded bg-card border border-line px-1.5 py-0.5 text-xs select-all">{{ $problem['command'] }}</code></p>
        </div>
    @endforeach

    <form action="{{ route('injongepay.platform.background-jobs.update') }}" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <x-common.table :headers="[__('injongepay::injongepay.job_on'), __('body.name'), __('injongepay::injongepay.job_timing'), __('body.status'), __('injongepay::injongepay.job_last_run')]">
            @foreach ($jobs as $job)
                <tr>
                    <td class="px-5 py-3"><x-common.checkbox :name="'jobs['.$job['key'].'][enabled]'" :checked="old('jobs.'.$job['key'].'.enabled', $job['enabled'])" /></td>
                    <td class="px-5 py-3">
                        <p class="font-semibold text-ink">{{ __('injongepay::injongepay.job_'.$job['key']) }}</p>
                        <p class="text-sm text-muted">{{ __('injongepay::injongepay.job_'.$job['key'].'_hint') }}</p>
                    </td>
                    <td class="px-5 py-3 min-w-44">
                        @if ($job['options'])
                            <x-common.select :name="'jobs['.$job['key'].'][timing]'" :options="collect($job['options'])->mapWithKeys(fn ($m) => [$m => trans_choice('injongepay::injongepay.job_every_minutes', $m, ['count' => $m])])->all()" :value="$job['timing']" hide-placeholder compact />
                        @else
                            <x-common.input :name="'jobs['.$job['key'].'][timing]'" type="time" :value="$job['timing']" :hint="__('injongepay::injongepay.job_daily_at')" compact />
                        @endif
                    </td>
                    <td class="px-5 py-3"><x-common.badge :color="match ($job['status']) { 'ok', 'running' => 'success', 'failed', 'late' => 'danger', 'off' => 'warning', default => 'muted' }">{{ __('injongepay::injongepay.job_status_'.$job['status']) }}</x-common.badge></td>
                    <td class="px-5 py-3 text-muted">{{ $job['last_finished_at']?->diffForHumans() ?? __('injongepay::injongepay.job_never_run') }}</td>
                </tr>
            @endforeach
        </x-common.table>
        <p class="text-sm text-muted">{{ __('injongepay::injongepay.background_jobs_off_warning') }}</p>
        <x-common.button type="submit">{{ __('body.save') }}</x-common.button>
    </form>
</x-layouts.app>
